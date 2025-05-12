// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Hide splash screen after 2 seconds
    setTimeout(function() {
        const splashScreen = document.getElementById('splash-screen');
        if (splashScreen) {
            splashScreen.style.display = 'none';
        }
    }, 2000);

    // Product modal functionality
    const productModal = document.getElementById('product-modal');
    if (productModal) {
        // Close modal when clicking outside the content
        productModal.addEventListener('click', function(e) {
            if (e.target === productModal) {
                closeProductModal();
            }
        });
    }
    
    // Payment method selection
    const paymentMethods = document.querySelectorAll('.payment-method');
    if (paymentMethods.length > 0) {
        paymentMethods.forEach(method => {
            method.addEventListener('click', function() {
                // Remove active class from all payment methods
                paymentMethods.forEach(m => m.classList.remove('active'));
                // Add active class to clicked payment method
                this.classList.add('active');
            });
        });
    }
});

// Open product modal
function openProductModal() {
    const modal = document.getElementById('product-modal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
}

// Close product modal
function closeProductModal() {
    const modal = document.getElementById('product-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Enable scrolling
    }
}

// Increment quantity in product modal
function incrementQuantity() {
    const quantityInput = document.getElementById('quantity');
    if (quantityInput) {
        quantityInput.value = parseInt(quantityInput.value) + 1;
    }
}

// Decrement quantity in product modal
function decrementQuantity() {
    const quantityInput = document.getElementById('quantity');
    if (quantityInput && parseInt(quantityInput.value) > 1) {
        quantityInput.value = parseInt(quantityInput.value) - 1;
    }
}

// Filter businesses by category
function filterBusinesses(category) {
    const filterButtons = document.querySelectorAll('.filter-button');
    const businessItems = document.querySelectorAll('.business-list-item');
    
    // Update active filter button
    filterButtons.forEach(button => {
        if (button.textContent === category || (button.textContent === 'All' && category === 'All')) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
    
    // Filter businesses
    if (category === 'All') {
        businessItems.forEach(item => {
            item.style.display = 'flex';
        });
    } else {
        businessItems.forEach(item => {
            const businessType = item.querySelector('.business-type').textContent;
            if (businessType.includes(category)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
}

// Toggle between list and map view
function toggleView(view) {
    const viewButtons = document.querySelectorAll('.view-button');
    const listingsSection = document.querySelector('.business-listings');
    
    // Update active view button
    viewButtons.forEach(button => {
        if (button.textContent.includes(view)) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
    
    // Toggle view
    if (view === 'Map') {
        // Here we would show the map and hide the list
        // For now, we'll just add a placeholder
        listingsSection.innerHTML = '<div class="map-placeholder">Map view coming soon</div>';
    } else {
        // Reload the list view (in a real app, this would be more sophisticated)
        location.reload();
    }
}

// Show order tab (active or completed)
function showOrderTab(tab) {
    const tabButtons = document.querySelectorAll('.tab-button');
    const activeOrders = document.getElementById('active-orders');
    const completedOrders = document.getElementById('completed-orders');
    const noOrders = document.getElementById('no-orders');
    
    // Update active tab button
    tabButtons.forEach(button => {
        if (button.textContent.toLowerCase() === tab) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
    
    // Show/hide appropriate sections
    if (tab === 'active') {
        if (activeOrders) activeOrders.style.display = 'block';
        if (completedOrders) completedOrders.style.display = 'none';
        
        // Check if there are active orders
        const hasActiveOrders = activeOrders && activeOrders.querySelectorAll('.order-card').length > 0;
        if (noOrders) noOrders.style.display = hasActiveOrders ? 'none' : 'block';
    } else {
        if (activeOrders) activeOrders.style.display = 'none';
        if (completedOrders) completedOrders.style.display = 'block';
        
        // Check if there are completed orders
        const hasCompletedOrders = completedOrders && completedOrders.querySelectorAll('.order-card').length > 0;
        if (noOrders) noOrders.style.display = hasCompletedOrders ? 'none' : 'block';
    }
}

// Add event listeners for filter buttons
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-button');
    if (filterButtons) {
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterBusinesses(this.textContent);
            });
        });
    }
    
    // Add event listeners for view toggle
    const viewButtons = document.querySelectorAll('.view-button');
    if (viewButtons) {
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const view = this.textContent.includes('Map') ? 'Map' : 'List';
                toggleView(view);
            });
        });
    }
    
    // Initialize cart functionality
    initCart();
    
    // Initialize order tabs
    const tabButtons = document.querySelectorAll('.tab-button');
    if (tabButtons) {
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                showOrderTab(this.textContent.toLowerCase());
            });
        });
    }
});

// Cart functionality
function initCart() {
    // Add to cart buttons
    const addToCartButtons = document.querySelectorAll('.add-to-cart-button');
    if (addToCartButtons) {
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent opening the modal
                
                // In a real app, we would add the product to the cart
                // For now, just show a notification
                showNotification('Product added to cart');
            });
        });
    }
    
    // Quantity buttons in cart
    const quantityButtons = document.querySelectorAll('.cart-item-quantity .quantity-button');
    if (quantityButtons) {
        quantityButtons.forEach(button => {
            button.addEventListener('click', function() {
                const quantitySpan = this.parentElement.querySelector('span');
                let quantity = parseInt(quantitySpan.textContent);
                
                if (this.textContent === '+') {
                    quantity++;
                } else if (this.textContent === '-' && quantity > 1) {
                    quantity--;
                }
                
                quantitySpan.textContent = quantity;
                updateCartTotal();
            });
        });
    }
    
    // Remove item buttons
    const removeButtons = document.querySelectorAll('.remove-item');
    if (removeButtons) {
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const cartItem = this.closest('.cart-item');
                cartItem.style.height = '0';
                cartItem.style.opacity = '0';
                cartItem.style.margin = '0';
                cartItem.style.padding = '0';
                cartItem.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    cartItem.remove();
                    updateCartTotal();
                    
                    // Check if cart is empty
                    const cartItems = document.querySelectorAll('.cart-item');
                    if (cartItems.length === 0) {
                        const cartItemsSection = document.querySelector('.cart-items');
                        if (cartItemsSection) {
                            cartItemsSection.innerHTML = '<div class="empty-cart">Your cart is empty</div>';
                        }
                    }
                }, 300);
            });
        });
    }
}

// Update cart total
function updateCartTotal() {
    // In a real app, this would calculate the actual total
    // For now, just show a static value
    const subtotalElement = document.querySelector('.summary-row:first-child span:last-child');
    const totalElement = document.querySelector('.summary-row.total span:last-child');
    
    if (subtotalElement && totalElement) {
        subtotalElement.textContent = '₱395.00';
        totalElement.textContent = '₱415.00';
    }
}

// Show notification
function showNotification(message, isError = false) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.textContent = message;
    
    // Add styles
    notification.style.position = 'fixed';
    notification.style.bottom = '100px';
    notification.style.left = '50%';
    notification.style.transform = 'translateX(-50%)';
    notification.style.backgroundColor = isError ? 'var(--color-danger, #e74c3c)' : 'var(--color-primary)';
    notification.style.color = 'white';
    notification.style.padding = '10px 20px';
    notification.style.borderRadius = 'var(--border-radius)';
    notification.style.boxShadow = 'var(--shadow-md)';
    notification.style.zIndex = '1000';
    notification.style.opacity = '0';
    notification.style.transition = 'opacity 0.3s ease';
    
    // Add to body
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 10);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Cancel Order Modal Functions
function showCancelOrderModal(orderId) {
    // Set the order ID to cancel
    document.getElementById('order-id-to-cancel').value = orderId;
    
    // Show the modal
    const modal = document.getElementById('cancel-order-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
    
    // Set up the reason dropdown change event
    const reasonSelect = document.getElementById('cancel-reason');
    if (reasonSelect) {
        reasonSelect.addEventListener('change', function() {
            const otherReasonContainer = document.getElementById('other-reason-container');
            if (this.value === 'Other') {
                otherReasonContainer.style.display = 'block';
            } else {
                otherReasonContainer.style.display = 'none';
            }
        });
    }
}

function closeCancelOrderModal() {
    const modal = document.getElementById('cancel-order-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Enable scrolling
    }
    
    // Reset form
    document.getElementById('cancel-reason').value = 'Changed my mind';
    document.getElementById('other-reason').value = '';
    document.getElementById('other-reason-container').style.display = 'none';
}

function cancelOrder() {
    // Get order ID and reason
    const orderId = document.getElementById('order-id-to-cancel').value;
    let reason = document.getElementById('cancel-reason').value;
    
    // If reason is 'Other', get the text from the textarea
    if (reason === 'Other') {
        const otherReason = document.getElementById('other-reason').value.trim();
        if (otherReason) {
            reason = otherReason;
        }
    }
    
    // Show loading state
    const confirmButton = document.querySelector('#cancel-order-modal .danger-button');
    if (confirmButton) {
        const originalText = confirmButton.textContent;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Processing...';
    }
    
    // Send cancellation request to server
    fetch('api/cancel_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: orderId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success notification
            showNotification('Order cancelled successfully');
            
            // Close modal
            closeCancelOrderModal();
            
            // Reload page after a short delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            // Show error notification
            showNotification('Error: ' + data.message, true);
            
            // Reset button
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.textContent = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error cancelling order. Please try again.', true);
        
        // Reset button
        if (confirmButton) {
            confirmButton.disabled = false;
            confirmButton.textContent = originalText;
        }
    });
}

// Place Order function
function placeOrder() {
    // Show loading state
    const orderButton = document.querySelector('.place-order-button-container button');
    if (orderButton) {
        const originalText = orderButton.innerHTML;
        orderButton.disabled = true;
        orderButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    // Get selected payment method
    const paymentMethod = document.querySelector('.payment-method.active')?.dataset.method || 'cash';
    
    // Get pickup details
    const pickupDate = document.getElementById('pickup_date')?.textContent;
    const pickupTime = document.getElementById('pickup_time')?.textContent;
    
    // Get order note
    const orderNote = document.getElementById('order_note')?.value || '';
    
    // Prepare data
    const orderData = {
        payment_method: paymentMethod,
        pickup_date: pickupDate,
        pickup_time: pickupTime,
        note: orderNote
    };
    
    // Send order to server
    fetch('api/process_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success notification
            showNotification('Order placed successfully!');
            
            // Redirect to orders page after a short delay
            setTimeout(() => {
                window.location.href = 'orders.php';
            }, 1500);
        } else {
            // Show error notification
            showNotification('Error: ' + data.message);
            
            // Reset button
            if (orderButton) {
                orderButton.disabled = false;
                orderButton.innerHTML = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error processing order. Please try again.');
        
        // Reset button
        if (orderButton) {
            orderButton.disabled = false;
            orderButton.innerHTML = originalText;
        }
    });
}