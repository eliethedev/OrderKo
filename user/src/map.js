/**
 * OrderKo Map Functionality
 * Uses Leaflet.js for interactive maps
 */

// Store map instances
let currentMap = null;
let currentMarker = null;
let defaultLocation = [14.5995, 120.9842]; // Default Manila coordinates

/**
 * Initialize a map in the specified container
 * @param {string} containerId - The ID of the HTML element to contain the map
 * @param {Array} initialLocation - [latitude, longitude] array for initial center
 * @param {number} zoom - Initial zoom level
 * @param {boolean} allowInteraction - Whether to allow user interaction
 * @returns {Object} The map instance
 */
function initMap(containerId, initialLocation = null, zoom = 13, allowInteraction = true) {
    // Use provided location or default
    const location = initialLocation || defaultLocation;
    
    // Create map instance
    const map = L.map(containerId, {
        center: location,
        zoom: zoom,
        zoomControl: allowInteraction,
        dragging: allowInteraction,
        touchZoom: allowInteraction,
        doubleClickZoom: allowInteraction,
        scrollWheelZoom: allowInteraction,
        boxZoom: allowInteraction,
        tap: allowInteraction,
        keyboard: allowInteraction
    });
    
    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Store as current map
    currentMap = map;
    
    return map;
}

/**
 * Add a marker to the map
 * @param {Object} map - The map instance
 * @param {Array} position - [latitude, longitude] array for marker position
 * @param {boolean} draggable - Whether the marker can be dragged
 * @param {string} popupText - Optional text for popup
 * @returns {Object} The marker instance
 */
function addMarker(map, position, draggable = false, popupText = null) {
    // Create marker
    const marker = L.marker(position, {
        draggable: draggable
    }).addTo(map);
    
    // Add popup if text provided
    if (popupText) {
        marker.bindPopup(popupText).openPopup();
    }
    
    // Store as current marker
    currentMarker = marker;
    
    return marker;
}

/**
 * Create a fully interactive location picker map
 * @param {string} containerId - The ID of the HTML element to contain the map
 * @param {function} onLocationSelect - Callback function when location is selected
 * @param {Array} initialLocation - Optional initial location
 */
function createLocationPicker(containerId, onLocationSelect, initialLocation = null) {
    // Initialize map
    const map = initMap(containerId, initialLocation);
    
    // Add initial marker if location provided
    let marker = null;
    if (initialLocation) {
        marker = addMarker(map, initialLocation, true);
    }
    
    // Handle map clicks to set marker
    map.on('click', function(e) {
        const position = [e.latlng.lat, e.latlng.lng];
        
        // Remove existing marker if any
        if (marker) {
            map.removeLayer(marker);
        }
        
        // Add new marker
        marker = addMarker(map, position, true);
        
        // Get address from coordinates
        getAddressFromCoordinates(position[0], position[1])
            .then(address => {
                marker.bindPopup(address).openPopup();
                
                // Call callback with location data
                if (onLocationSelect) {
                    onLocationSelect({
                        latitude: position[0],
                        longitude: position[1],
                        address: address
                    });
                }
            })
            .catch(error => {
                console.error('Error getting address:', error);
                marker.bindPopup('Selected location').openPopup();
                
                // Call callback with coordinates only
                if (onLocationSelect) {
                    onLocationSelect({
                        latitude: position[0],
                        longitude: position[1],
                        address: 'Unknown location'
                    });
                }
            });
    });
    
    // Handle marker drag end
    if (marker) {
        marker.on('dragend', function(e) {
            const position = [e.target.getLatLng().lat, e.target.getLatLng().lng];
            
            // Get address from coordinates
            getAddressFromCoordinates(position[0], position[1])
                .then(address => {
                    marker.bindPopup(address).openPopup();
                    
                    // Call callback with location data
                    if (onLocationSelect) {
                        onLocationSelect({
                            latitude: position[0],
                            longitude: position[1],
                            address: address
                        });
                    }
                })
                .catch(error => {
                    console.error('Error getting address:', error);
                    marker.bindPopup('Selected location').openPopup();
                    
                    // Call callback with coordinates only
                    if (onLocationSelect) {
                        onLocationSelect({
                            latitude: position[0],
                            longitude: position[1],
                            address: 'Unknown location'
                        });
                    }
                });
        });
    }
    
    // Add search control
    const searchControl = L.Control.geocoder({
        defaultMarkGeocode: false
    }).addTo(map);
    
    searchControl.on('markgeocode', function(e) {
        const position = [e.geocode.center.lat, e.geocode.center.lng];
        
        // Remove existing marker if any
        if (marker) {
            map.removeLayer(marker);
        }
        
        // Add new marker
        marker = addMarker(map, position, true, e.geocode.name);
        
        // Zoom to location
        map.fitBounds(e.geocode.bbox);
        
        // Call callback with location data
        if (onLocationSelect) {
            onLocationSelect({
                latitude: position[0],
                longitude: position[1],
                address: e.geocode.name
            });
        }
    });
    
    // Add current location button
    const locationButton = L.control({position: 'bottomright'});
    locationButton.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
        div.innerHTML = '<a class="leaflet-control-locate" href="#" title="Show my location"><i class="fas fa-location-arrow"></i></a>';
        
        div.onclick = function() {
            getUserLocation()
                .then(location => {
                    const position = [location.latitude, location.longitude];
                    
                    // Remove existing marker if any
                    if (marker) {
                        map.removeLayer(marker);
                    }
                    
                    // Add new marker
                    marker = addMarker(map, position, true, location.address);
                    
                    // Center map on location
                    map.setView(position, 16);
                    
                    // Call callback with location data
                    if (onLocationSelect) {
                        onLocationSelect(location);
                    }
                })
                .catch(error => {
                    console.error('Error getting location:', error);
                    alert('Could not get your location. Please try again or select manually.');
                });
            
            return false;
        };
        
        return div;
    };
    locationButton.addTo(map);
    
    return {
        map: map,
        marker: marker
    };
}

/**
 * Create a static map (non-interactive) to display a location
 * @param {string} containerId - The ID of the HTML element to contain the map
 * @param {Array} location - [latitude, longitude] array for the location
 * @param {string} popupText - Optional text for popup
 */
function createStaticMap(containerId, location, popupText = null) {
    // Initialize map with interaction disabled
    const map = initMap(containerId, location, 15, false);
    
    // Add marker
    addMarker(map, location, false, popupText);
    
    return map;
}

/**
 * Update an existing map with a new location
 * @param {Object} map - The map instance
 * @param {Array} location - [latitude, longitude] array for the new center
 * @param {boolean} addMarker - Whether to add a marker at the location
 */
function updateMapLocation(map, location, addMarker = true) {
    // Center map on new location
    map.setView(location, 15);
    
    // Add marker if requested
    if (addMarker) {
        // Remove existing marker if any
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }
        
        // Add new marker
        addMarker(map, location);
    }
}

/**
 * Search for an address and display on map
 * @param {string} address - The address to search for
 * @param {Object} map - The map instance
 * @param {function} callback - Optional callback with location data
 */
function searchAddress(address, map, callback = null) {
    // Use Nominatim for geocoding
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                const location = [parseFloat(data[0].lat), parseFloat(data[0].lon)];
                
                // Update map
                updateMapLocation(map, location);
                
                // Call callback if provided
                if (callback) {
                    callback({
                        latitude: location[0],
                        longitude: location[1],
                        address: data[0].display_name
                    });
                }
            } else {
                alert('Address not found. Please try a different search term.');
            }
        })
        .catch(error => {
            console.error('Error searching address:', error);
            alert('An error occurred while searching. Please try again.');
        });
}
