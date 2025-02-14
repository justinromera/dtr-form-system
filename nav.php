<div 
    class="fixed top-1/2 left-2.5 transform -translate-y-1/2 flex flex-col items-center bg-white shadow-md w-16 pt-4 pb-4 rounded-[20px] border border-gray-300">
    <!-- Toggle Button (Top Icon) -->
    <button id="menuToggle" class="p-3 mb-2 bg-gray-100 rounded-full shadow">
      <div class="w-6 h-6 grid grid-cols-2 gap-1">
        <div class="bg-black w-full h-full"></div>
        <div class="bg-black w-full h-full"></div>
        <div class="bg-black w-full h-full"></div>
        <div class="bg-black w-full h-full"></div>
      </div>
    </button>

    <!-- Collapsible Navigation Items -->
    <div id="navBar" class="flex flex-col items-center nav-collapsed">
        <button onclick="window.location.href='admin.php'" class="p-4 icon" style="transition-delay: 0.1s;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-10 0a1 1 0 001 1h3m10-11h-3m-4 0h-3m-4 0h-3"></path>
          </svg>
        </button>
        <button onclick="window.location.href='scheduleFiling.php'" class="p-4 icon" style="transition-delay: 0.2s;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 4h10a2 2 0 012 2v10a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2zm3 4h4m-4 4h4"></path>
          </svg>
        </button>
        <button class="p-4 icon" data-bs-toggle="modal" data-bs-target="#addUserModal" style="transition-delay: 0.3s;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        <button class="p-4 icon" data-bs-toggle="modal" data-bs-target="#removeUserModal" style="transition-delay: 0.4s;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
        <button onclick="window.location.href='logout.php'" class="p-4 icon" style="transition-delay: 0.5s;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1"></path>
          </svg>
        </button>
    </div>
</div>