<script setup>
import { ref, onMounted } from 'vue'

// Tab management
const activeTab = ref('working-hours')

// Working Hours state
const workingHours = ref([])
const loading = ref(false)
const message = ref('')
const messageType = ref('success')

// Break periods management
const breakPeriods = ref({}) // { workingHourId: [breakPeriods] }
const loadingBreakPeriods = ref({}) // { workingHourId: boolean }
const expandedBreakPeriods = ref({}) // { workingHourId: boolean }
const editingBreakPeriod = ref(null) // { id, workingHourId, start_time, end_time, name, is_active }
const newBreakPeriod = ref({}) // { workingHourId: { start_time, end_time, name } }

const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const newDay = ref({
  day_of_week: null,
  start_time: '09:00',
  end_time: '17:00',
  is_active: true
})

// Services state
const services = ref([])
const loadingServices = ref(false)
const editingService = ref(null) // { id, name, duration_minutes, price }
const newService = ref({
  name: '',
  duration_minutes: 30,
  price: 0
})

// Bookings state
const bookings = ref([])
const loadingBookings = ref(false)
const bookingFilters = ref({
  date: '',
  status: '',
  date_from: '',
  date_to: ''
})

onMounted(async () => {
  await fetchWorkingHours()
  await fetchServices()
  await fetchBookings()
})

async function fetchWorkingHours() {
  loading.value = true
  try {
    const response = await fetch('/api/admin/working-hours')
    const data = await response.json()
    // Handle the response structure: { locale: 'en', working_hours: [...] }
    workingHours.value = data.working_hours || data || []
    
    // Initialize break periods form state for each working hour (but don't initialize breakPeriods array)
    workingHours.value.forEach(hour => {
      // Don't initialize breakPeriods array - let it be undefined until fetched
      // This ensures fetchBreakPeriods is called when expanding
      if (!newBreakPeriod.value[hour.id]) {
        newBreakPeriod.value[hour.id] = {
          start_time: hour.start_time ? hour.start_time.substring(0, 5) : '12:00',
          end_time: hour.end_time ? hour.end_time.substring(0, 5) : '13:00',
          name: ''
        }
      }
    })
  } catch (error) {
    showMessage('Failed to load working hours', 'danger')
    workingHours.value = []
  } finally {
    loading.value = false
  }
}

async function updateWorkingHour(hour) {
  try {
    // Normalize time format: remove seconds if present (HTML inputs use H:i format)
    const startTime = hour.start_time ? hour.start_time.substring(0, 5) : null
    const endTime = hour.end_time ? hour.end_time.substring(0, 5) : null
    
    const response = await fetch(`/api/admin/working-hours/${hour.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        start_time: startTime,
        end_time: endTime,
        is_active: hour.is_active
      })
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || 'Failed to update')
    }
    
    const updatedData = await response.json()
    // Update the local state with the response data to ensure consistency
    const index = workingHours.value.findIndex(h => h.id === hour.id)
    if (index !== -1) {
      workingHours.value[index] = { ...workingHours.value[index], ...updatedData }
    }
    
    showMessage('Working hours updated successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

async function addWorkingDay() {
  if (newDay.value.day_of_week === null) {
    showMessage('Please select a day', 'warning')
    return
  }
  
  try {
    const response = await fetch('/api/admin/working-hours', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(newDay.value)
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.message || 'Failed to add working day')
    }
    
    workingHours.value.push(data)
    workingHours.value.sort((a, b) => a.day_of_week - b.day_of_week)
    
    // Initialize break periods state for the new working hour
    if (!breakPeriods.value[data.id]) {
      breakPeriods.value[data.id] = []
    }
    if (!newBreakPeriod.value[data.id]) {
      newBreakPeriod.value[data.id] = {
        start_time: data.start_time ? data.start_time.substring(0, 5) : '12:00',
        end_time: data.end_time ? data.end_time.substring(0, 5) : '13:00',
        name: ''
      }
    }
    
    // Reset form
    newDay.value = {
      day_of_week: null,
      start_time: '09:00',
      end_time: '17:00',
      is_active: true
    }
    
    showMessage('Working day added successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

async function deleteWorkingHour(id) {
  if (!confirm('Are you sure you want to delete this working day?')) {
    return
  }
  
  try {
    const response = await fetch(`/api/admin/working-hours/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      throw new Error('Failed to delete')
    }
    
    workingHours.value = workingHours.value.filter(h => h.id !== id)
    
    // Clean up break periods state
    delete breakPeriods.value[id]
    delete newBreakPeriod.value[id]
    delete expandedBreakPeriods.value[id]
    delete loadingBreakPeriods.value[id]
    
    showMessage('Working day deleted', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

function getAvailableDays() {
  // Ensure workingHours.value is an array
  const hours = Array.isArray(workingHours.value) ? workingHours.value : []
  const usedDays = hours.map(h => h.day_of_week)
  return days
    .map((name, index) => ({ name, value: index }))
    .filter(day => !usedDays.includes(day.value))
}

// Break Periods Functions
async function fetchBreakPeriods(workingHourId) {
  if (loadingBreakPeriods.value[workingHourId]) return
  
  loadingBreakPeriods.value[workingHourId] = true
  try {
    const response = await fetch(`/api/admin/working-hours/${workingHourId}/break-periods`)
    if (!response.ok) {
      throw new Error('Failed to load break periods')
    }
    const data = await response.json()
    breakPeriods.value[workingHourId] = data || []
  } catch (error) {
    showMessage('Failed to load break periods', 'danger')
    breakPeriods.value[workingHourId] = []
  } finally {
    loadingBreakPeriods.value[workingHourId] = false
  }
}

function toggleBreakPeriods(workingHourId) {
  expandedBreakPeriods.value[workingHourId] = !expandedBreakPeriods.value[workingHourId]
  
  // Fetch break periods when expanding (unless already loading)
  if (expandedBreakPeriods.value[workingHourId] && !loadingBreakPeriods.value[workingHourId]) {
    // Always fetch when expanding to ensure we have the latest data
    // This handles the case where breakPeriods was initialized as empty array on page load
    fetchBreakPeriods(workingHourId)
  }
}

async function createBreakPeriod(workingHourId) {
  const breakPeriod = newBreakPeriod.value[workingHourId]
  
  if (!breakPeriod.start_time || !breakPeriod.end_time) {
    showMessage('Please fill in start and end times', 'warning')
    return
  }
  
  if (breakPeriod.start_time >= breakPeriod.end_time) {
    showMessage('End time must be after start time', 'warning')
    return
  }
  
  try {
    const response = await fetch(`/api/admin/working-hours/${workingHourId}/break-periods`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        start_time: breakPeriod.start_time,
        end_time: breakPeriod.end_time,
        name: breakPeriod.name || null
      })
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      const errorMessage = data.message || 'Failed to create break period'
      const errors = data.errors || {}
      let errorText = errorMessage
      
      if (Object.keys(errors).length > 0) {
        errorText = Object.values(errors).flat().join(', ')
      }
      
      throw new Error(errorText)
    }
    
    // Add to local state
    if (!breakPeriods.value[workingHourId]) {
      breakPeriods.value[workingHourId] = []
    }
    breakPeriods.value[workingHourId].push(data)
    breakPeriods.value[workingHourId].sort((a, b) => a.start_time.localeCompare(b.start_time))
    
    // Reset form
    const workingHour = workingHours.value.find(h => h.id === workingHourId)
    newBreakPeriod.value[workingHourId] = {
      start_time: workingHour.start_time ? workingHour.start_time.substring(0, 5) : '12:00',
      end_time: workingHour.end_time ? workingHour.end_time.substring(0, 5) : '13:00',
      name: ''
    }
    
    showMessage('Break period created successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

function startEditBreakPeriod(breakPeriod) {
  editingBreakPeriod.value = {
    id: breakPeriod.id,
    workingHourId: breakPeriod.working_hour_id,
    start_time: breakPeriod.start_time ? breakPeriod.start_time.substring(0, 5) : '',
    end_time: breakPeriod.end_time ? breakPeriod.end_time.substring(0, 5) : '',
    name: breakPeriod.name || '',
    is_active: breakPeriod.is_active !== undefined ? breakPeriod.is_active : true
  }
}

function cancelEditBreakPeriod() {
  editingBreakPeriod.value = null
}

async function updateBreakPeriod() {
  if (!editingBreakPeriod.value) return
  
  if (!editingBreakPeriod.value.start_time || !editingBreakPeriod.value.end_time) {
    showMessage('Please fill in start and end times', 'warning')
    return
  }
  
  if (editingBreakPeriod.value.start_time >= editingBreakPeriod.value.end_time) {
    showMessage('End time must be after start time', 'warning')
    return
  }
  
  try {
    const response = await fetch(`/api/admin/break-periods/${editingBreakPeriod.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        start_time: editingBreakPeriod.value.start_time,
        end_time: editingBreakPeriod.value.end_time,
        name: editingBreakPeriod.value.name || null,
        is_active: editingBreakPeriod.value.is_active
      })
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      const errorMessage = data.message || 'Failed to update break period'
      const errors = data.errors || {}
      let errorText = errorMessage
      
      if (Object.keys(errors).length > 0) {
        errorText = Object.values(errors).flat().join(', ')
      }
      
      throw new Error(errorText)
    }
    
    // Update local state
    const workingHourId = editingBreakPeriod.value.workingHourId
    const index = breakPeriods.value[workingHourId]?.findIndex(bp => bp.id === editingBreakPeriod.value.id)
    if (index !== -1) {
      breakPeriods.value[workingHourId][index] = data
      breakPeriods.value[workingHourId].sort((a, b) => a.start_time.localeCompare(b.start_time))
    }
    
    editingBreakPeriod.value = null
    showMessage('Break period updated successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

async function deleteBreakPeriod(breakPeriod) {
  if (!confirm('Are you sure you want to delete this break period?')) {
    return
  }
  
  try {
    const response = await fetch(`/api/admin/break-periods/${breakPeriod.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      throw new Error('Failed to delete break period')
    }
    
    // Remove from local state
    const workingHourId = breakPeriod.working_hour_id
    breakPeriods.value[workingHourId] = breakPeriods.value[workingHourId].filter(
      bp => bp.id !== breakPeriod.id
    )
    
    showMessage('Break period deleted successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

function showMessage(text, type) {
  message.value = text
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 5000)
}

// Services Management Functions
async function fetchServices() {
  loadingServices.value = true
  try {
    const response = await fetch('/api/services')
    if (!response.ok) {
      throw new Error('Failed to load services')
    }
    services.value = await response.json()
  } catch (error) {
    showMessage('Failed to load services', 'danger')
    services.value = []
  } finally {
    loadingServices.value = false
  }
}

async function createService() {
  if (!newService.value.name || !newService.value.duration_minutes || newService.value.price === null) {
    showMessage('Please fill in all fields', 'warning')
    return
  }
  
  if (newService.value.duration_minutes < 5 || newService.value.duration_minutes > 480) {
    showMessage('Duration must be between 5 and 480 minutes', 'warning')
    return
  }
  
  if (newService.value.price < 0) {
    showMessage('Price must be 0 or greater', 'warning')
    return
  }
  
  try {
    const response = await fetch('/api/admin/services', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        name: newService.value.name,
        duration_minutes: parseInt(newService.value.duration_minutes),
        price: parseFloat(newService.value.price)
      })
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      const errorMessage = data.message || 'Failed to create service'
      const errors = data.errors || {}
      let errorText = errorMessage
      
      if (Object.keys(errors).length > 0) {
        errorText = Object.values(errors).flat().join(', ')
      }
      
      throw new Error(errorText)
    }
    
    services.value.push(data)
    services.value.sort((a, b) => a.name.localeCompare(b.name))
    
    // Reset form
    newService.value = {
      name: '',
      duration_minutes: 30,
      price: 0
    }
    
    showMessage('Service created successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

function startEditService(service) {
  editingService.value = {
    id: service.id,
    name: service.name,
    duration_minutes: service.duration_minutes,
    price: parseFloat(service.price)
  }
}

function cancelEditService() {
  editingService.value = null
}

async function updateService() {
  if (!editingService.value) return
  
  if (!editingService.value.name || !editingService.value.duration_minutes || editingService.value.price === null) {
    showMessage('Please fill in all fields', 'warning')
    return
  }
  
  if (editingService.value.duration_minutes < 5 || editingService.value.duration_minutes > 480) {
    showMessage('Duration must be between 5 and 480 minutes', 'warning')
    return
  }
  
  if (editingService.value.price < 0) {
    showMessage('Price must be 0 or greater', 'warning')
    return
  }
  
  try {
    const response = await fetch(`/api/admin/services/${editingService.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        name: editingService.value.name,
        duration_minutes: parseInt(editingService.value.duration_minutes),
        price: parseFloat(editingService.value.price)
      })
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      const errorMessage = data.message || 'Failed to update service'
      const errors = data.errors || {}
      let errorText = errorMessage
      
      if (Object.keys(errors).length > 0) {
        errorText = Object.values(errors).flat().join(', ')
      }
      
      throw new Error(errorText)
    }
    
    // Update local state
    const index = services.value.findIndex(s => s.id === editingService.value.id)
    if (index !== -1) {
      services.value[index] = data
      services.value.sort((a, b) => a.name.localeCompare(b.name))
    }
    
    editingService.value = null
    showMessage('Service updated successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

async function deleteService(service) {
  if (!confirm(`Are you sure you want to delete "${service.name}"? This action cannot be undone.`)) {
    return
  }
  
  try {
    const response = await fetch(`/api/admin/services/${service.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || 'Failed to delete service')
    }
    
    services.value = services.value.filter(s => s.id !== service.id)
    showMessage('Service deleted successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

// Bookings Management Functions
async function fetchBookings() {
  loadingBookings.value = true
  try {
    const params = new URLSearchParams()
    
    if (bookingFilters.value.date) {
      params.append('date', bookingFilters.value.date)
    }
    if (bookingFilters.value.status) {
      params.append('status', bookingFilters.value.status)
    }
    if (bookingFilters.value.date_from) {
      params.append('date_from', bookingFilters.value.date_from)
    }
    if (bookingFilters.value.date_to) {
      params.append('date_to', bookingFilters.value.date_to)
    }
    
    const queryString = params.toString()
    const url = `/api/admin/bookings${queryString ? `?${queryString}` : ''}`
    
    const response = await fetch(url)
    if (!response.ok) {
      throw new Error('Failed to load bookings')
    }
    
    const data = await response.json()
    bookings.value = data.bookings || []
  } catch (error) {
    showMessage('Failed to load bookings', 'danger')
    bookings.value = []
  } finally {
    loadingBookings.value = false
  }
}

function applyBookingFilters() {
  fetchBookings()
}

function clearBookingFilters() {
  bookingFilters.value = {
    date: '',
    status: '',
    date_from: '',
    date_to: ''
  }
  fetchBookings()
}

async function updateBookingStatus(booking, newStatus) {
  try {
    const response = await fetch(`/api/admin/bookings/${booking.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        status: newStatus
      })
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || 'Failed to update booking status')
    }
    
    const data = await response.json()
    
    // Update local state
    const index = bookings.value.findIndex(b => b.id === booking.id)
    if (index !== -1) {
      bookings.value[index] = data.booking
    }
    
    showMessage('Booking status updated successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

async function deleteBooking(booking) {
  const serviceName = booking.service?.name || 'Unknown Service'
  const bookingDate = new Date(booking.booking_date).toLocaleDateString()
  
  if (!confirm(`Are you sure you want to delete the booking for "${serviceName}" on ${bookingDate}? This action cannot be undone.`)) {
    return
  }
  
  try {
    const response = await fetch(`/api/admin/bookings/${booking.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || 'Failed to delete booking')
    }
    
    bookings.value = bookings.value.filter(b => b.id !== booking.id)
    showMessage('Booking deleted successfully', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}

function formatTime(timeString) {
  if (!timeString) return '-'
  // Handle both H:i and H:i:s formats
  const time = timeString.substring(0, 5)
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours, 10)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${minutes} ${ampm}`
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'confirmed':
      return 'bg-success'
    case 'pending':
      return 'bg-warning'
    case 'cancelled':
      return 'bg-secondary'
    default:
      return 'bg-secondary'
  }
}
</script>

<template>
  <div class="container">
    <div class="row">
      <div class="col-lg-10 mx-auto">
        <h2 class="mb-4">Admin Panel</h2>
        
        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item" role="presentation">
            <button
              :class="['nav-link', activeTab === 'working-hours' ? 'active' : '']"
              @click="activeTab = 'working-hours'"
              type="button"
            >
              Working Hours
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              :class="['nav-link', activeTab === 'services' ? 'active' : '']"
              @click="activeTab = 'services'"
              type="button"
            >
              Services
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              :class="['nav-link', activeTab === 'bookings' ? 'active' : '']"
              @click="activeTab = 'bookings'"
              type="button"
            >
              Bookings
            </button>
          </li>
        </ul>
        
        <!-- Message Alert -->
        <div v-if="message" :class="['alert', `alert-${messageType}`, 'alert-dismissible']" role="alert">
          {{ message }}
          <button type="button" class="btn-close" @click="message = ''"></button>
        </div>
        
        <!-- Working Hours Tab -->
        <div v-if="activeTab === 'working-hours'">
          <h3 class="mb-3">Working Hours Management</h3>
        
        <!-- Loading -->
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
        
        <!-- Working Hours List -->
        <div v-else class="card shadow-sm mb-4">
          <div class="card-body">
            <h5 class="card-title mb-3">Current Working Hours</h5>
            
            <div v-if="workingHours.length === 0" class="alert alert-info">
              No working hours configured yet. Add one below.
            </div>
            
            <div v-else>
              <div v-for="hour in workingHours" :key="hour.id" class="mb-3">
                <div class="card border">
                  <div class="card-body">
                    <div class="row align-items-center">
                      <div class="col-md-2">
                        <strong>{{ days[hour.day_of_week] }}</strong>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small">Start Time</label>
                        <input 
                          v-model="hour.start_time" 
                          type="time"
                          class="form-control form-control-sm"
                          @change="updateWorkingHour(hour)"
                        />
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small">End Time</label>
                        <input 
                          v-model="hour.end_time" 
                          type="time"
                          class="form-control form-control-sm"
                          @change="updateWorkingHour(hour)"
                        />
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <div class="form-check form-switch">
                          <input 
                            v-model="hour.is_active" 
                            type="checkbox"
                            class="form-check-input"
                            @change="updateWorkingHour(hour)"
                          />
                          <label class="form-check-label small">
                            {{ hour.is_active ? 'Active' : 'Inactive' }}
                          </label>
                        </div>
                      </div>
                      <div class="col-md-4 text-end">
                        <button 
                          @click="toggleBreakPeriods(hour.id)"
                          class="btn btn-sm btn-outline-primary me-2"
                        >
                          <span v-if="!expandedBreakPeriods[hour.id]">Manage Break Periods</span>
                          <span v-else>Hide Break Periods</span>
                        </button>
                        <button 
                          @click="deleteWorkingHour(hour.id)"
                          class="btn btn-sm btn-danger"
                        >
                          Delete Day
                        </button>
                      </div>
                    </div>
                    
                    <!-- Break Periods Section -->
                    <div v-if="expandedBreakPeriods[hour.id]" class="mt-4 pt-3 border-top">
                      <h6 class="mb-3">Break Periods for {{ days[hour.day_of_week] }}</h6>
                      
                      <!-- Loading Break Periods -->
                      <div v-if="loadingBreakPeriods[hour.id]" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
                        </div>
                      </div>
                      
                      <!-- Break Periods List -->
                      <div v-else>
                        <div v-if="!breakPeriods[hour.id] || breakPeriods[hour.id].length === 0" 
                             class="alert alert-info mb-3">
                          No break periods configured for this day.
                        </div>
                        
                        <div v-else class="table-responsive mb-3">
                          <table class="table table-sm table-bordered">
                            <thead>
                              <tr>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="bp in breakPeriods[hour.id]" :key="bp.id">
                                <td v-if="editingBreakPeriod?.id !== bp.id">
                                  {{ bp.start_time ? bp.start_time.substring(0, 5) : '' }}
                                </td>
                                <td v-else>
                                  <input 
                                    v-model="editingBreakPeriod.start_time"
                                    type="time"
                                    class="form-control form-control-sm"
                                  />
                                </td>
                                
                                <td v-if="editingBreakPeriod?.id !== bp.id">
                                  {{ bp.end_time ? bp.end_time.substring(0, 5) : '' }}
                                </td>
                                <td v-else>
                                  <input 
                                    v-model="editingBreakPeriod.end_time"
                                    type="time"
                                    class="form-control form-control-sm"
                                  />
                                </td>
                                
                                <td v-if="editingBreakPeriod?.id !== bp.id">
                                  {{ bp.name || '-' }}
                                </td>
                                <td v-else>
                                  <input 
                                    v-model="editingBreakPeriod.name"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="Break name (optional)"
                                  />
                                </td>
                                
                                <td v-if="editingBreakPeriod?.id !== bp.id">
                                  <span :class="['badge', bp.is_active ? 'bg-success' : 'bg-secondary']">
                                    {{ bp.is_active ? 'Active' : 'Inactive' }}
                                  </span>
                                </td>
                                <td v-else>
                                  <div class="form-check form-switch">
                                    <input 
                                      v-model="editingBreakPeriod.is_active"
                                      type="checkbox"
                                      class="form-check-input"
                                    />
                                  </div>
                                </td>
                                
                                <td>
                                  <div v-if="editingBreakPeriod?.id !== bp.id" class="btn-group btn-group-sm">
                                    <button 
                                      @click="startEditBreakPeriod(bp)"
                                      class="btn btn-outline-primary"
                                    >
                                      Edit
                                    </button>
                                    <button 
                                      @click="deleteBreakPeriod(bp)"
                                      class="btn btn-outline-danger"
                                    >
                                      Delete
                                    </button>
                                  </div>
                                  <div v-else class="btn-group btn-group-sm">
                                    <button 
                                      @click="updateBreakPeriod"
                                      class="btn btn-success"
                                    >
                                      Save
                                    </button>
                                    <button 
                                      @click="cancelEditBreakPeriod"
                                      class="btn btn-secondary"
                                    >
                                      Cancel
                                    </button>
                                  </div>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                      
                      <!-- Add New Break Period Form -->
                      <div class="card bg-light">
                        <div class="card-body">
                          <h6 class="card-title small mb-3">Add New Break Period</h6>
                          <div class="row g-2">
                            <div class="col-md-3">
                              <label class="form-label small">Start Time</label>
                              <input 
                                v-model="newBreakPeriod[hour.id].start_time"
                                type="time"
                                class="form-control form-control-sm"
                                :min="hour.start_time ? hour.start_time.substring(0, 5) : ''"
                                :max="hour.end_time ? hour.end_time.substring(0, 5) : ''"
                              />
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small">End Time</label>
                              <input 
                                v-model="newBreakPeriod[hour.id].end_time"
                                type="time"
                                class="form-control form-control-sm"
                                :min="hour.start_time ? hour.start_time.substring(0, 5) : ''"
                                :max="hour.end_time ? hour.end_time.substring(0, 5) : ''"
                              />
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small">Name (Optional)</label>
                              <input 
                                v-model="newBreakPeriod[hour.id].name"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="e.g., Lunch Break"
                              />
                            </div>
                            <div class="col-md-3">
                              <label class="form-label small d-block">&nbsp;</label>
                              <button 
                                @click="createBreakPeriod(hour.id)"
                                class="btn btn-primary btn-sm w-100"
                              >
                                Add Break
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Add New Working Day -->
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3">Add Working Day</h5>
            
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Day</label>
                <select v-model.number="newDay.day_of_week" class="form-select">
                  <option :value="null">Select day...</option>
                  <option 
                    v-for="day in getAvailableDays()" 
                    :key="day.value"
                    :value="day.value"
                  >
                    {{ day.name }}
                  </option>
                </select>
              </div>
              
              <div class="col-md-3">
                <label class="form-label">Start Time</label>
                <input 
                  v-model="newDay.start_time" 
                  type="time"
                  class="form-control"
                />
              </div>
              
              <div class="col-md-3">
                <label class="form-label">End Time</label>
                <input 
                  v-model="newDay.end_time" 
                  type="time"
                  class="form-control"
                />
              </div>
              
              <div class="col-md-3">
                <label class="form-label d-block">&nbsp;</label>
                <button 
                  @click="addWorkingDay"
                  class="btn btn-primary w-100"
                >
                  Add Day
                </button>
              </div>
            </div>
          </div>
        </div>
        </div>
        
        <!-- Services Tab -->
        <div v-if="activeTab === 'services'">
          <h3 class="mb-3">Services Management</h3>
          
          <!-- Loading -->
          <div v-if="loadingServices" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
          
          <!-- Services List -->
          <div v-else class="card shadow-sm mb-4">
            <div class="card-body">
              <h5 class="card-title mb-3">Current Services</h5>
              
              <div v-if="services.length === 0" class="alert alert-info">
                No services configured yet. Add one below.
              </div>
              
              <div v-else class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Duration</th>
                      <th>Price</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="service in services" :key="service.id">
                      <td v-if="editingService?.id !== service.id">
                        <strong>{{ service.name }}</strong>
                      </td>
                      <td v-else>
                        <input
                          v-model="editingService.name"
                          type="text"
                          class="form-control form-control-sm"
                          placeholder="Service name"
                        />
                      </td>
                      
                      <td v-if="editingService?.id !== service.id">
                        {{ service.duration_minutes }} minutes
                      </td>
                      <td v-else>
                        <div class="input-group input-group-sm">
                          <input
                            v-model.number="editingService.duration_minutes"
                            type="number"
                            class="form-control"
                            min="5"
                            max="480"
                            step="5"
                          />
                          <span class="input-group-text">min</span>
                        </div>
                      </td>
                      
                      <td v-if="editingService?.id !== service.id">
                        ${{ parseFloat(service.price).toFixed(2) }}
                      </td>
                      <td v-else>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">$</span>
                          <input
                            v-model.number="editingService.price"
                            type="number"
                            class="form-control"
                            min="0"
                            step="0.01"
                          />
                        </div>
                      </td>
                      
                      <td>
                        <div v-if="editingService?.id !== service.id" class="btn-group btn-group-sm">
                          <button
                            @click="startEditService(service)"
                            class="btn btn-outline-primary"
                          >
                            Edit
                          </button>
                          <button
                            @click="deleteService(service)"
                            class="btn btn-outline-danger"
                          >
                            Delete
                          </button>
                        </div>
                        <div v-else class="btn-group btn-group-sm">
                          <button
                            @click="updateService"
                            class="btn btn-success"
                          >
                            Save
                          </button>
                          <button
                            @click="cancelEditService"
                            class="btn btn-secondary"
                          >
                            Cancel
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <!-- Add New Service -->
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title mb-3">Add New Service</h5>
              
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Service Name</label>
                  <input
                    v-model="newService.name"
                    type="text"
                    class="form-control"
                    placeholder="e.g., Haircut"
                    required
                  />
                </div>
                
                <div class="col-md-3">
                  <label class="form-label">Duration (minutes)</label>
                  <div class="input-group">
                    <input
                      v-model.number="newService.duration_minutes"
                      type="number"
                      class="form-control"
                      min="5"
                      max="480"
                      step="5"
                      required
                    />
                    <span class="input-group-text">min</span>
                  </div>
                  <small class="form-text text-muted">Between 5-480 minutes</small>
                </div>
                
                <div class="col-md-3">
                  <label class="form-label">Price</label>
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input
                      v-model.number="newService.price"
                      type="number"
                      class="form-control"
                      min="0"
                      step="0.01"
                      required
                    />
                  </div>
                </div>
                
                <div class="col-md-2">
                  <label class="form-label d-block">&nbsp;</label>
                  <button
                    @click="createService"
                    class="btn btn-primary w-100"
                  >
                    Add Service
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Bookings Tab -->
        <div v-if="activeTab === 'bookings'">
          <h3 class="mb-3">Bookings Management</h3>
          
          <!-- Filters -->
          <div class="card shadow-sm mb-4">
            <div class="card-body">
              <h5 class="card-title mb-3">Filters</h5>
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Date</label>
                  <input
                    v-model="bookingFilters.date"
                    type="date"
                    class="form-control"
                    @change="applyBookingFilters"
                  />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Status</label>
                  <select
                    v-model="bookingFilters.status"
                    class="form-select"
                    @change="applyBookingFilters"
                  >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date From</label>
                  <input
                    v-model="bookingFilters.date_from"
                    type="date"
                    class="form-control"
                    @change="applyBookingFilters"
                  />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date To</label>
                  <input
                    v-model="bookingFilters.date_to"
                    type="date"
                    class="form-control"
                    @change="applyBookingFilters"
                  />
                </div>
              </div>
              <div class="mt-3">
                <button
                  @click="clearBookingFilters"
                  class="btn btn-outline-secondary btn-sm"
                >
                  Clear Filters
                </button>
              </div>
            </div>
          </div>
          
          <!-- Loading -->
          <div v-if="loadingBookings" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
          
          <!-- Bookings Table -->
          <div v-else class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title mb-3">All Bookings ({{ bookings.length }})</h5>
              
              <div v-if="bookings.length === 0" class="alert alert-info">
                No bookings found. Try adjusting your filters.
              </div>
              
              <div v-else class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Service</th>
                      <th>Client Email</th>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="booking in bookings" :key="booking.id">
                      <td>{{ booking.id }}</td>
                      <td>
                        <strong>{{ booking.service?.name || 'N/A' }}</strong>
                        <br>
                        <small class="text-muted">
                          {{ booking.service?.duration_minutes || 0 }} min - 
                          ${{ parseFloat(booking.service?.price || 0).toFixed(2) }}
                        </small>
                      </td>
                      <td>{{ booking.client_email }}</td>
                      <td>{{ formatDate(booking.booking_date) }}</td>
                      <td>
                        {{ formatTime(booking.start_time) }} - 
                        {{ formatTime(booking.end_time) }}
                      </td>
                      <td>
                        <select
                          :value="booking.status"
                          @change="updateBookingStatus(booking, $event.target.value)"
                          :class="['form-select', 'form-select-sm', `border-${getStatusBadgeClass(booking.status).replace('bg-', '')}`]"
                        >
                          <option value="pending">Pending</option>
                          <option value="confirmed">Confirmed</option>
                          <option value="cancelled">Cancelled</option>
                        </select>
                      </td>
                      <td>
                        <button
                          @click="deleteBooking(booking)"
                          class="btn btn-sm btn-outline-danger"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.table input[type="time"] {
  min-width: 100px;
}

.card.border {
  border: 1px solid #dee2e6 !important;
}

.border-top {
  border-top: 2px solid #dee2e6 !important;
}
</style>