<script setup>
import { ref } from 'vue';
</script>

<template>
    <div class="main-h-screen bg-gray-900 text-white">
        <div class="h-20 bg-gray-800 flex items-center justify-center">
            <p class="text-gray-400">navbar is comming</p>
        </div>

       <!-- main Content -->
        <main >
            <slot />
        </main>

        <!-- footer -->
         <div class="py-12 bg-gray-800 text-center">
       <p class="text-gray-400">Footer is coming</p>

        </div>
    </div>
</template>
