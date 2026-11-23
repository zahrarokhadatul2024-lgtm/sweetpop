/**
 * SWEETPOP - Main Client Interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Toast Notification Helper
    window.showSweetToast = function(message, icon = '🍓') {
        let toast = document.querySelector('.sweet-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'sweet-toast';
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<span style="font-size: 1.4rem;">${icon}</span> <div><strong>SweetPop</strong><br><small style="color:#666;">${message}</small></div>`;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3500);
    };

    // 2. Add to Cart Buttons
    document.querySelectorAll('.btn-add-cart').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const productId = btn.dataset.id;
            const productTitle = btn.dataset.title || 'Product';
            
            fetch(`cart.php?action=add&id=${productId}&ajax=1`, {
                method: 'GET'
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showSweetToast(`${productTitle} added to your bag! ♡`, '✨');
                    // update bag badge
                    const badge = document.querySelector('.badge-count');
                    if (badge) {
                        badge.textContent = data.cartCount;
                    }
                }
            })
            .catch(err => {
                // Fallback direct navigate
                window.location.href = `cart.php?action=add&id=${productId}`;
            });
        });
    });

    // 3. Quantity Increment/Decrement
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const isPlus = btn.classList.contains('qty-plus');
            const container = btn.closest('.qty-control');
            const input = container.querySelector('.qty-input');
            let val = parseInt(input.value) || 1;

            if (isPlus) {
                val++;
            } else {
                if (val > 1) val--;
            }
            input.value = val;

            const form = btn.closest('form');
            if (form && form.classList.contains('cart-update-form')) {
                form.submit();
            }
        });
    });
});
