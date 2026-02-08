/* js/action_menu.js - Robust Dropdown Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // Helper: Close all menus and reset z-indexes
    const closeAllMenus = () => {
        document.querySelectorAll('.action-menu.active').forEach(menu => {
            menu.classList.remove('active');
            // Reset the row's z-index so it settles back into the table
            const row = menu.closest('tr');
            if(row) {
                row.style.zIndex = '';
                row.style.position = '';
                row.style.transform = '';
            }
        });
    };

    // Global Click Listener
    document.addEventListener('click', (e) => {
        
        const trigger = e.target.closest('.btn-trigger-menu');
        const activeMenu = e.target.closest('.action-menu');

        // SCENARIO 1: Clicking the "Three Dots" Button
        if (trigger) {
            e.stopPropagation(); // Stop bubbling

            const container = trigger.closest('.action-container');
            const menu = container.querySelector('.action-menu');
            const row = trigger.closest('tr');
            const isAlreadyOpen = menu.classList.contains('active');

            // 1. Close any other open menus first
            closeAllMenus();

            // 2. If it wasn't already open, open it now
            if (!isAlreadyOpen) {
                menu.classList.add('active');
                
                // CRITICAL FIX: Force this row to float above others
                if(row) {
                    row.style.zIndex = '1000';
                    row.style.position = 'relative'; 
                    // 'transform' forces a new stacking context
                    row.style.transform = 'translateZ(10px)'; 
                }
            }
        } 
        
        // SCENARIO 2: Clicking inside the menu (e.g., "Edit")
        else if (activeMenu) {
            // Allow the button's 'onclick' event to fire first, then close
            setTimeout(() => {
                closeAllMenus();
            }, 100);
        }

        // SCENARIO 3: Clicking anywhere else
        else {
            closeAllMenus();
        }
    });
});