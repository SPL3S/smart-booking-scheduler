<script setup>
import { ref, onMounted } from 'vue'

const workingHours = ref([])
const loading = ref(false)
const message = ref('')
const messageType = ref('success')

const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const newDay = ref({
  day_of_week: null,
  start_time: '09:00',
  end_time: '17:00',
  is_active: true
})

onMounted(async () => {
  await fetchWorkingHours()
})

async function fetchWorkingHours() {
  loading.value = true
  try {
    const response = await fetch('/api/admin/working-hours')
    workingHours.value = await response.json()
  } catch (error) {
    showMessage('Failed to load working hours', 'danger')
  } finally {
    loading.value = false
  }
}

async function updateWorkingHour(hour) {
  try {
    const response = await fetch(`/api/admin/working-hours/${hour.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        start_time: hour.start_time,
        end_time: hour.end_time,
        is_active: hour.is_active
      })
    })
    
    if (!response.ok) {
      throw new Error('Failed to update')
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
    showMessage('Working day deleted', 'success')
  } catch (error) {
    showMessage(error.message, 'danger')
  }
}

function getAvailableDays() {
  const usedDays = workingHours.value.map(h => h.day_of_week)
  return days
    .map((name, index) => ({ name, value: index }))
    .filter(day => !usedDays.includes(day.value))
}

function showMessage(text, type) {
  message.value = text
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 5000)
}
</script>

<template>
  <div class="container">
    <div class="row">
      <div class="col-lg-10 mx-auto">
        <h2 class="mb-4">Working Hours Management</h2>
        
        <!-- Message Alert -->
        <div v-if="message" :class="['alert', `alert-${messageType}`, 'alert-dismissible']" role="alert">
          {{ message }}
          <button type="button" class="btn-close" @click="message = ''"></button>
        </div>
        
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
            
            <div v-else class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="hour in workingHours" :key="hour.id">
                    <td class="fw-bold">{{ days[hour.day_of_week] }}</td>
                    <td>
                      <input 
                        v-model="hour.start_time" 
                        type="time"
                        class="form-control form-control-sm"
                        @change="updateWorkingHour(hour)"
                      />
                    </td>
                    <td>
                      <input 
                        v-model="hour.end_time" 
                        type="time"
                        class="form-control form-control-sm"
                        @change="updateWorkingHour(hour)"
                      />
                    </td>
                    <td>
                      <div class="form-check form-switch">
                        <input 
                          v-model="hour.is_active" 
                          type="checkbox"
                          class="form-check-input"
                          @change="updateWorkingHour(hour)"
                        />
                        <label class="form-check-label">
                          {{ hour.is_active ? 'Active' : 'Inactive' }}
                        </label>
                      </div>
                    </td>
                    <td>
                      <button 
                        @click="deleteWorkingHour(hour.id)"
                        class="btn btn-sm btn-danger"
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
    </div>
  </div>
</template>

<style scoped>
.table input[type="time"] {
  min-width: 100px;
}
</style>