/**
 * Location handling for OrderKo
 */

// Store location data
let userLocation = {
    latitude: 14.5995, // Default Manila coordinates
    longitude: 120.9842,
    address: "Manila, Philippines",
    isDefault: true
};

// Get user's current location
function getUserLocation() {
    return new Promise((resolve, reject) => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        address: "Current Location",
                        isDefault: false
                    };
                    
                    // Try to get address from coordinates
                    getAddressFromCoordinates(position.coords.latitude, position.coords.longitude)
                        .then(address => {
                            userLocation.address = address;
                            resolve(userLocation);
                        })
                        .catch(() => {
                            // If reverse geocoding fails, still resolve with coordinates
                            resolve(userLocation);
                        });
                },
                (error) => {
                    console.warn("Error getting location:", error.message);
                    resolve(userLocation); // Resolve with default location
                },
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
        } else {
            console.warn("Geolocation is not supported by this browser");
            resolve(userLocation); // Resolve with default location
        }
    });
}

// Get address from coordinates using reverse geocoding
function getAddressFromCoordinates(latitude, longitude) {
    return new Promise((resolve, reject) => {
        // Using Nominatim OpenStreetMap service for reverse geocoding
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.display_name) {
                    resolve(data.display_name);
                } else {
                    reject("No address found");
                }
            })
            .catch(error => {
                console.error("Error getting address:", error);
                reject(error);
            });
    });
}

// Calculate distance between two points
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius of the earth in km
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
        Math.sin(dLon/2) * Math.sin(dLon/2); 
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
    const distance = R * c; // Distance in km
    return parseFloat(distance.toFixed(1));
}

// Convert degrees to radians
function deg2rad(deg) {
    return deg * (Math.PI/180);
}

// Calculate delivery fee based on distance
function calculateDeliveryFee(distance) {
    // Base fee
    const baseFee = 50;
    
    // Additional fee per kilometer
    const perKmFee = 10;
    
    // Calculate total fee (base fee + distance fee)
    let totalFee = baseFee;
    
    // Add distance fee if distance is greater than 2 km
    if (distance > 2) {
        totalFee += (distance - 2) * perKmFee;
    }
    
    return parseFloat(totalFee.toFixed(2));
}

// Save user location to session
function saveUserLocation(location) {
    // Use fetch to send the data to the server
    const formData = new FormData();
    formData.append('user_latitude', location.latitude);
    formData.append('user_longitude', location.longitude);
    formData.append('user_address', location.address);
    
    return fetch(window.location.href, {
        method: 'POST',
        body: formData
    });
}

// Update delivery details based on location
function updateDeliveryDetails(userLocation, businessLocation) {
    // Calculate distance
    const distance = calculateDistance(
        userLocation.latitude, 
        userLocation.longitude, 
        businessLocation.latitude, 
        businessLocation.longitude
    );
    
    // Calculate delivery fee
    const deliveryFee = calculateDeliveryFee(distance);
    
    // Update UI elements
    document.getElementById('delivery_address').textContent = userLocation.address;
    document.getElementById('delivery_distance').textContent = `Distance: ${distance} km`;
    document.getElementById('delivery_fee').textContent = `₱${deliveryFee.toFixed(2)}`;
    document.getElementById('delivery_fee_summary').textContent = `₱${deliveryFee.toFixed(2)}`;
    
    // Update estimated delivery time based on distance
    let estimatedTime = "30-45 minutes";
    if (distance > 5) {
        estimatedTime = "45-60 minutes";
    } else if (distance > 10) {
        estimatedTime = "60-90 minutes";
    }
    document.getElementById('delivery_time').textContent = estimatedTime;
    
    // Return the calculated values for use in other functions
    return {
        distance: distance,
        deliveryFee: deliveryFee
    };
}
