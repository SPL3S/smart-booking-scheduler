<script setup>
import { ref, watch, onMounted, computed } from 'vue'

const services = ref([])
const selectedDate = ref('')
const selectedService = ref(null)
const availableSlots = ref([])
const selectedSlot = ref(null)
const clientEmail = ref('')
const loading = ref(false)
const loadingSlots = ref(false)
const message = ref('')
const messageType = ref('success')
const workingDays = ref([]) // Array of day_of_week values (0=Sunday, 1=Monday, etc.)

// Get today's date in local timezone (not UTC)
const todayDateObj = new Date()
const today = `${todayDateObj.getFullYear()}-${String(todayDateObj.getMonth() + 1).padStart(2, '0')}-${String(todayDateObj.getDate()).padStart(2, '0')}`

const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

const currentMonthYear = computed(() => {
  const date = new Date(currentYear.value, currentMonth.value)
  return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const calendarDays = computed(() => {
  const firstDay = new Date(currentYear.value, currentMonth.value, 1)
  const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0)
  const prevLastDay = new Date(currentYear.value, currentMonth.value, 0)
  
  const days = []
  const todayDate = new Date()
  todayDate.setHours(0, 0, 0, 0)
  
  // Helper function to check if a date has working hours
  const hasWorkingHours = (date) => {
    if (workingDays.value.length === 0) return false
    const dayOfWeek = date.getDay() // 0=Sunday, 1=Monday, etc.
    return workingDays.value.includes(dayOfWeek)
  }
  
  // Helper function to format date as YYYY-MM-DD in local timezone (not UTC)
  const formatDateLocal = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  
  // Previous month days
  const firstDayOfWeek = firstDay.getDay()
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const day = prevLastDay.getDate() - i
    const date = new Date(currentYear.value, currentMonth.value - 1, day)
    const isPast = date < todayDate
    const hasHours = hasWorkingHours(date)
    const fullDate = formatDateLocal(date)
    days.push({
      day,
      date: date.toISOString(),
      fullDate: fullDate,
      isCurrentMonth: false,
      isPast,
      hasWorkingHours: hasHours,
      isDisabled: isPast || !hasHours
    })
  }
  
  // Current month days
  for (let day = 1; day <= lastDay.getDate(); day++) {
    const date = new Date(currentYear.value, currentMonth.value, day)
    const isPast = date < todayDate
    const hasHours = hasWorkingHours(date)
    const fullDate = formatDateLocal(date)
    days.push({
      day,
      date: date.toISOString(),
      fullDate: fullDate,
      isCurrentMonth: true,
      isPast,
      hasWorkingHours: hasHours,
      isDisabled: isPast || !hasHours
    })
  }
  
  // Next month days
  const remainingDays = 42 - days.length
  for (let day = 1; day <= remainingDays; day++) {
    const date = new Date(currentYear.value, currentMonth.value + 1, day)
    const isPast = date < todayDate
    const hasHours = hasWorkingHours(date)
    const fullDate = formatDateLocal(date)
    days.push({
      day,
      date: date.toISOString(),
      fullDate: fullDate,
      isCurrentMonth: false,
      isPast,
      hasWorkingHours: hasHours,
      isDisabled: isPast || !hasHours
    })
  }
  
  return days
})

function changeMonth(delta) {
  currentMonth.value += delta
  if (currentMonth.value > 11) {
    currentMonth.value = 0
    currentYear.value++
  } else if (currentMonth.value < 0) {
    currentMonth.value = 11
    currentYear.value--
  }
}

function selectDate(day) {
  if (day.isDisabled || !day.isCurrentMonth) return
  selectedDate.value = day.fullDate
}

onMounted(async () => {
  await fetchServices()
  await fetchWorkingDays()
})

async function fetchServices() {
  try {
    const response = await fetch('/api/services')
    services.value = await response.json()
  } catch (error) {
    showMessage('Failed to load services', 'danger')
  }
}

async function fetchWorkingDays() {
  try {
    const response = await fetch('/api/working-days')
    const data = await response.json()
    workingDays.value = data.working_days || []
  } catch (error) {
    console.error('Failed to load working days:', error)
    workingDays.value = []
  }
}

watch([selectedDate, selectedService], async ([date, service]) => {
  if (date && service) {
    await fetchAvailableSlots()
  } else {
    availableSlots.value = []
    selectedSlot.value = null
  }
})

async function fetchAvailableSlots() {
  loadingSlots.value = true
  availableSlots.value = []
  selectedSlot.value = null
  
  try {
    const response = await fetch(
      `/api/available-slots?date=${selectedDate.value}&service_id=${selectedService.value}`
    )
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || 'Failed to load time slots')
    }
    
    const data = await response.json()
    availableSlots.value = data.slots
    
    if (data.slots.length === 0) {
      showMessage('No available slots for this date', 'info')
    }
  } catch (error) {
    showMessage(error.message || 'Failed to load time slots', 'danger')
    availableSlots.value = []
  } finally {
    loadingSlots.value = false
  }
}

async function bookAppointment() {
  if (!selectedSlot.value || !clientEmail.value) {
    showMessage('Please fill all fields', 'warning')
    return
  }
  
  loading.value = true
  
  try {
    const response = await fetch('/api/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        service_id: selectedService.value,
        booking_date: selectedDate.value,
        start_time: selectedSlot.value.start_time,
        end_time: selectedSlot.value.end_time,
        client_email: clientEmail.value
      })
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.message || 'Booking failed')
    }
    
    showMessage('Booking successful! ✅', 'success')
    
    // Reset form
    selectedSlot.value = null
    clientEmail.value = ''
    await fetchAvailableSlots()
    
  } catch (error) {
    showMessage(error.message, 'danger')
  } finally {
    loading.value = false
  }
}

function showMessage(text, type) {
  message.value = text
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 5000)
}

function selectSlot(slot) {
  selectedSlot.value = slot
}
</script>

<template>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12" style="max-width: 450px;">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h4 class="card-title mb-4 text-center">Book an Appointment</h4>
            
            <div v-if="message" :class="['alert', `alert-${messageType}`, 'alert-dismissible']" role="alert">
              {{ message }}
              <button type="button" class="btn-close" @click="message = ''"></button>
            </div>
            
            <div class="mb-3">
              <label class="form-label fw-bold small">1. Select Service</label>
              <select v-model="selectedService" class="form-select">
                <option :value="null">Choose a service...</option>
                <option 
                  v-for="service in services" 
                  :key="service.id"
                  :value="service.id"
                >
                  {{ service.name }} ({{ service.duration_minutes }} min) - ${{ service.price }}
                </option>
              </select>
            </div>
            
            <div class="mb-3" v-if="selectedService">
              <label class="form-label fw-bold small">2. Select Date</label>
              <div class="calendar-container mx-auto">
                <div class="calendar">
                  <div class="calendar-header">
                    <button @click="changeMonth(-1)" class="btn btn-sm btn-outline-secondary" type="button">‹</button>
                    <span class="fw-bold">{{ currentMonthYear }}</span>
                    <button @click="changeMonth(1)" class="btn btn-sm btn-outline-secondary" type="button">›</button>
                  </div>
                  <div class="calendar-weekdays">
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="weekday">
                      {{ day }}
                    </div>
                  </div>
                  <div class="calendar-days">
                    <button
                      v-for="day in calendarDays"
                      :key="day.date"
                      @click="selectDate(day)"
                      :disabled="day.isDisabled || !day.isCurrentMonth"
                      :class="[
                        'calendar-day',
                        { 'selected': selectedDate === day.fullDate },
                        { 'disabled': day.isDisabled || !day.isCurrentMonth }
                      ]"
                      type="button"
                    >
                      {{ day.day }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
            
            <div v-if="selectedDate && selectedService" class="mb-3">
              <label class="form-label fw-bold small">3. Choose Time Slot</label>
              
              <div v-if="loadingSlots" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              
              <div v-else-if="availableSlots.length === 0" class="alert alert-info">
                No available slots for this date. Try another day.
              </div>
              
              <div v-else class="d-flex flex-wrap gap-2 justify-content-center">
                <button
                  v-for="slot in availableSlots"
                  :key="slot.start_time"
                  @click="selectSlot(slot)"
                  :class="[
                    'btn time-slot-btn btn-sm',
                    selectedSlot?.start_time === slot.start_time 
                      ? 'btn-primary' 
                      : 'btn-outline-primary'
                  ]"
                >
                  {{ slot.start_time }} - {{ slot.end_time }}
                </button>
              </div>
            </div>
            
            <div v-if="selectedSlot" class="mb-3">
              <label class="form-label fw-bold small">4. Your Email</label>
              <input 
                v-model="clientEmail" 
                type="email" 
                class="form-control"
                placeholder="your@email.com"
                required
              >
            </div>
            
            <div v-if="selectedSlot && clientEmail" class="d-grid">
              <button 
                @click="bookAppointment"
                :disabled="loading"
                class="btn btn-success"
              >
                <span v-if="loading">
                  <span class="spinner-border spinner-border-sm me-2"></span>
                  Booking...
                </span>
                <span v-else>Confirm Booking</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.card {
  border: none;
  border-radius: 12px;
}

.time-slot-btn {
  min-width: 110px;
  font-size: 0.875rem;
}

.gap-2 {
  gap: 0.5rem;
}

.calendar-container {
  max-width: 100%;
}

.calendar {
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 1rem;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-size: 0.95rem;
}

.calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.25rem;
  margin-bottom: 0.5rem;
}

.weekday {
  text-align: center;
  font-weight: 600;
  font-size: 0.8rem;
  color: #6c757d;
  padding: 0.5rem 0;
}

.calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.25rem;
}

.calendar-day {
  aspect-ratio: 1;
  border: 1px solid #e9ecef;
  background: white;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.calendar-day:hover:not(.disabled) {
  background: #e7f1ff;
  border-color: #0d6efd;
}

.calendar-day.selected {
  background: #0d6efd;
  color: white;
  border-color: #0d6efd;
  font-weight: bold;
}

.calendar-day.disabled {
  color: #dee2e6;
  cursor: not-allowed;
  background: #f8f9fa;
}

.form-label {
  font-size: 0.9rem;
}

.form-select,
.form-control {
  font-size: 0.9rem;
}
</style>