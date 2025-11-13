<script setup>
import { ref } from 'vue'
import BookingForm from './components/BookingForm.vue'
import AdminPanel from './components/AdminPanel.vue'

const currentView = ref('booking')
</script>

<template>
  <div id="app">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
      <div class="container">
        <span class="navbar-brand">
          <strong>Smart Booking Scheduler</strong>
        </span>
        <div class="navbar-nav ms-auto">
          <button 
            @click="currentView = 'booking'"
            :class="['btn btn-sm me-2', currentView === 'booking' ? 'btn-light' : 'btn-outline-light']"
          >
            Book Appointment
          </button>
          <button 
            @click="currentView = 'admin'"
            :class="['btn btn-sm', currentView === 'admin' ? 'btn-light' : 'btn-outline-light']"
          >
            Admin Panel
          </button>
        </div>
      </div>
    </nav>
    
    <BookingForm v-if="currentView === 'booking'" />
    <AdminPanel v-else />
  </div>
</template>

<style scoped>
.navbar-brand {
  font-size: 1.5rem;
}
</style>