// Global variables
let map;
let markers = new Map();
let hops = new Map();
let connections = new Map();
let overlays = [];
let boundaries = null;
let boundaryRectangle = null;
let navigationPath = null;
let currentLocationMarker = null;
let pathMarkers = [];
let currentLocation = 'main-campus';

function initMap(locationCode = 'main-campus') {
    currentLocation = locationCode;

    map = L.map('map').setView([51.505, -0.09], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    map.on('mousemove', function(e) {
        const coordsDisplay = document.getElementById('coordinatesDisplay');
        if (coordsDisplay) {
            coordsDisplay.innerHTML = `Lat: ${e.latlng.lat.toFixed(6)}, Lng: ${e.latlng.lng.toFixed(6)}`;
        }
    });

    setupEventListeners();
    loadMapData();
}

function setupEventListeners() {
    const startSelect = document.getElementById('startMarkerSelect');
    if (startSelect) {
        startSelect.addEventListener('change', function() {
            updateNavigateButton();
            highlightSelectedMarker('start', this.value);
        });
    }

    const endSelect = document.getElementById('endMarkerSelect');
    if (endSelect) {
        endSelect.addEventListener('change', function() {
            updateNavigateButton();
            highlightSelectedMarker('end', this.value);
        });
    }

    const currentDestSelect = document.getElementById('currentDestinationSelect');
    if (currentDestSelect) {
        currentDestSelect.addEventListener('change', function() {
            const currentNavBtn = document.getElementById('currentNavigateBtn');
            if (currentNavBtn) {
                currentNavBtn.disabled = !this.value;
            }
            highlightSelectedMarker('destination', this.value);
        });
    }

    const detailsSelect = document.getElementById('detailsMarkerSelect');
    if (detailsSelect) {
        detailsSelect.addEventListener('change', function() {
            const showDetailsBtn = document.getElementById('showDetailsBtn');
            if (showDetailsBtn) {
                showDetailsBtn.disabled = !this.value;
            }
        });
    }
}

function updateNavigateButton() {
    const startSelect = document.getElementById('startMarkerSelect');
    const endSelect = document.getElementById('endMarkerSelect');
    const navigateBtn = document.getElementById('navigateBtn');

    if (startSelect && endSelect && navigateBtn) {
        navigateBtn.disabled = !startSelect.value || !endSelect.value || startSelect.value === endSelect.value;
    }
}

function highlightSelectedMarker(type, markerId) {
    markers.forEach(marker => {
        if (marker._icon) {
            marker._icon.style.filter = '';
            marker._icon.style.transform = '';
        }
    });

    if (markerId && markers.has(parseInt(markerId))) {
        const marker = markers.get(parseInt(markerId));
        if (marker._icon) {
            switch(type) {
                case 'start':
                    marker._icon.style.filter = 'hue-rotate(120deg) brightness(1.2)';
                    marker._icon.style.transform = 'scale(1.1)';
                    break;
                case 'end':
                case 'destination':
                    marker._icon.style.filter = 'hue-rotate(-45deg) brightness(1.2)';
                    marker._icon.style.transform = 'scale(1.1)';
                    break;
            }
        }

        map.panTo([marker.markerData.latitude, marker.markerData.longitude]);
    }
}

async function loadMapData() {
    try {
        const response = await fetch(`/user/api/${currentLocation}/map-data`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        markers.forEach(marker => map.removeLayer(marker));
        connections.forEach(connection => map.removeLayer(connection));
        overlays.forEach(overlay => map.removeLayer(overlay));
        markers.clear();
        hops.clear();
        connections.clear();
        overlays = [];

        if (data.boundary) {
            setBoundaries(data.boundary);
        }

        if (data.hops && Array.isArray(data.hops)) {
            data.hops.forEach(hopData => {
                try {
                    if (hopData.type === 'marker') {
                        addMarkerToMap(hopData);
                    } else {
                        hops.set(hopData.id, hopData);
                    }
                } catch (error) {
                    console.error('Error adding marker to map:', error, hopData);
                }
            });
        }

        if (data.connections && Array.isArray(data.connections)) {
            data.connections.forEach(connectionData => {
                try {
                    addConnectionToMap(connectionData);
                } catch (error) {
                    console.error('Error adding connection to map:', error, connectionData);
                }
            });
        }

        if (data.overlays && Array.isArray(data.overlays)) {
            data.overlays.forEach(overlayData => {
                try {
                    addOverlayToMap(overlayData);
                } catch (error) {
                    console.error('Error adding overlay to map:', error, overlayData);
                }
            });
        }

        populateDropdowns();

    } catch (error) {
        console.error('Error loading map data:', error);
        showStatus('Failed to load map data', 'error');
    }
}

function populateDropdowns() {
    const startSelect = document.getElementById('startMarkerSelect');
    const endSelect = document.getElementById('endMarkerSelect');
    const currentDestSelect = document.getElementById('currentDestinationSelect');
    const detailsSelect = document.getElementById('detailsMarkerSelect');

    if (!startSelect || !endSelect || !currentDestSelect) return;

    // Clear existing options except the default
    startSelect.innerHTML = '<option value="">-- Select Start Location --</option>';
    endSelect.innerHTML = '<option value="">-- Select Destination --</option>';
    currentDestSelect.innerHTML = '<option value="">-- Select Destination --</option>';

    if (detailsSelect) {
        detailsSelect.innerHTML = '<option value="">-- Select Marker to View --</option>';
    }

    // Sort markers by name
    const sortedMarkers = Array.from(markers.values()).sort((a, b) =>
        a.markerData.name.localeCompare(b.markerData.name)
    );

    // Add markers to dropdowns
    sortedMarkers.forEach(marker => {
        const option1 = new Option(marker.markerData.name, marker.markerData.id);
        const option2 = new Option(marker.markerData.name, marker.markerData.id);
        const option3 = new Option(marker.markerData.name, marker.markerData.id);

        startSelect.add(option1);
        endSelect.add(option2);
        currentDestSelect.add(option3);

        if (detailsSelect) {
            const option4 = new Option(marker.markerData.name, marker.markerData.id);
            detailsSelect.add(option4);
        }
    });
}

function setBoundaries(boundaryData) {
    boundaries = boundaryData;

    const bounds = [[boundaries.south, boundaries.west], [boundaries.north, boundaries.east]];
    boundaryRectangle = L.rectangle(bounds, {
        color: '#ff0000',
        weight: 2,
        opacity: 0.3,
        fillOpacity: 0.05,
        dashArray: '5, 5',
        interactive: false
    }).addTo(map);

    map.setMaxBounds(bounds);
    map.fitBounds(bounds);
}

function addMarkerToMap(markerData) {
    const marker = L.marker([markerData.latitude, markerData.longitude]);

    let popupContent = `
        <div class="popup-content">
            <h4>${markerData.name}</h4>
            ${markerData.description ? `<p>${markerData.description.substring(0, 100)}${markerData.description.length > 100 ? '...' : ''}</p>` : ''}
            <div class="popup-buttons">
                <button class="popup-btn start" onclick="setAsStart(${markerData.id})">Set as Start</button>
                <button class="popup-btn destination" onclick="setAsDestination(${markerData.id})">Set as Destination</button>
                ${markerData.image_path || markerData.description ?
                    `<button class="popup-btn details" onclick="showMarkerDetails(${markerData.id})">📋 Details</button>` :
                    ''
                }
            </div>
        </div>
    `;

    marker.bindPopup(popupContent);
    marker.addTo(map);
    marker.markerData = markerData;

    markers.set(markerData.id, marker);
    hops.set(markerData.id, markerData);
}

function setAsStart(markerId) {
    const startSelect = document.getElementById('startMarkerSelect');
    if (startSelect) {
        startSelect.value = markerId;
        updateNavigateButton();
        highlightSelectedMarker('start', markerId);
    }
    map.closePopup();
}

function setAsDestination(markerId) {
    const endSelect = document.getElementById('endMarkerSelect');
    const currentDestSelect = document.getElementById('currentDestinationSelect');
    const currentNavBtn = document.getElementById('currentNavigateBtn');

    if (endSelect) endSelect.value = markerId;
    if (currentDestSelect) currentDestSelect.value = markerId;
    if (currentNavBtn) currentNavBtn.disabled = false;

    updateNavigateButton();
    highlightSelectedMarker('destination', markerId);
    map.closePopup();
}

function addConnectionToMap(connectionData) {
    const fromHop = connectionData.hop_from;
    const toHop = connectionData.hop_to;

    if (!fromHop || !toHop) {
        console.warn('Skipping connection - missing hop data:', connectionData);
        return;
    }

    const connection = L.polyline([
        [fromHop.latitude, fromHop.longitude],
        [toHop.latitude, toHop.longitude]
    ], {
        color: connectionData.color || '#0066cc',
        weight: connectionData.width || 5,
        opacity: 0.7,
        interactive: false
    }).addTo(map);

    connections.set(connectionData.id, connection);
}

function addOverlayToMap(overlayData) {
    const imageUrl = `/storage/${overlayData.image_path}`;
    const corners = overlayData.corners;

    const bounds = L.latLngBounds([
        [corners[2][0], corners[3][1]],
        [corners[0][0], corners[1][1]]
    ]);

    const overlay = L.imageOverlay(imageUrl, bounds, {
        opacity: overlayData.opacity,
        interactive: false
    }).addTo(map);

    overlays.push(overlay);
}

async function navigateMarkerToMarker() {
    const startId = document.getElementById('startMarkerSelect')?.value;
    const endId = document.getElementById('endMarkerSelect')?.value;

    if (!startId || !endId || startId === endId) {
        showStatus('Please select different start and destination markers', 'error');
        return;
    }

    showNavigationMode('Finding Route...', 'Please wait while we calculate the best path');

    try {
        console.log('Finding route from', startId, 'to', endId);

        const response = await fetch(`/user/api/${currentLocation}/find-path`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                start_id: parseInt(startId),
                end_id: parseInt(endId)
            })
        });

        const data = await response.json();
        console.log('Route response:', data);

        if (response.ok && data.success) {
            displayNavigationPath(data.path);
            showRouteInfo(data.start_hop, data.end_hop, data.path);
            hideNavigationMode();
            showStatus(`Route found: ${Math.round(data.total_distance)}m, ~${data.estimated_time}min`, 'success');
        } else {
            hideNavigationMode();
            showStatus(data.error || 'No route found between these locations', 'error');
        }
    } catch (error) {
        console.error('Error finding path:', error);
        hideNavigationMode();
        showStatus('Failed to find route: ' + error.message, 'error');
    }
}

async function navigateFromCurrent() {
    const destinationId = document.getElementById('currentDestinationSelect')?.value;
    if (!destinationId) {
        showStatus('Please select a destination', 'error');
        return;
    }

    clearNavigation();

    if (navigator.geolocation) {
        showNavigationMode('Getting Location...', 'Please wait while we find your location');

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
        };

        navigator.geolocation.getCurrentPosition(
            async position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                console.log('Got current location:', lat, lng);

                if (boundaries && !isWithinBoundaries(lat, lng)) {
                    hideNavigationMode();
                    showStatus('Your location is outside the campus boundaries', 'error');
                    return;
                }

                showCurrentLocation(lat, lng);
                showNavigationMode('Finding Route...', 'Calculating the best path from your location');

                try {
                    const response = await fetch(`/user/api/${currentLocation}/find-path`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            end_id: parseInt(destinationId),
                            current_lat: lat,
                            current_lng: lng
                        })
                    });

                    const data = await response.json();
                    console.log('Route from current location response:', data);

                    if (response.ok && data.success) {
                        displayNavigationPath(data.path);
                        showRouteInfo(null, data.end_hop, data.path, true);
                        hideNavigationMode();
                        showStatus(`Route found: ${Math.round(data.total_distance)}m, ~${data.estimated_time}min`, 'success');
                    } else {
                        hideNavigationMode();
                        showStatus(data.error || 'No route found to the destination', 'error');
                    }
                } catch (error) {
                    console.error('Error finding path:', error);
                    hideNavigationMode();
                    showStatus('Failed to find route: ' + error.message, 'error');
                }
            },
            error => {
                let errorMessage = 'Unable to get your location. ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage += 'Please allow location access.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage += 'Location information is unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage += 'Location request timed out.';
                        break;
                    default:
                        errorMessage += 'An unknown error occurred.';
                        break;
                }
                hideNavigationMode();
                showStatus(errorMessage, 'error');
            },
            options
        );
    } else {
        hideNavigationMode();
        showStatus('Geolocation is not supported by your browser', 'error');
    }
}

function isWithinBoundaries(lat, lng) {
    if (!boundaries) return true;
    return lat >= boundaries.south && lat <= boundaries.north &&
           lng >= boundaries.west && lng <= boundaries.east;
}

function showCurrentLocation(lat, lng) {
    if (currentLocationMarker) {
        map.removeLayer(currentLocationMarker);
    }

    currentLocationMarker = L.marker([lat, lng], {
        icon: L.divIcon({
            className: 'current-location-marker',
            html: `
                <div style="position: relative;">
                    <div style="width: 20px; height: 20px; background: #ff0000; border: 3px solid white; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>
                    <div class="current-location-pulse" style="position: absolute; top: -5px; left: -5px; width: 30px; height: 30px; background: rgba(255,0,0,0.2); border-radius: 50%;"></div>
                </div>
            `,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        })
    }).addTo(map);

    currentLocationMarker.bindPopup('<b>📍 Your Location</b>').openPopup();
    map.setView([lat, lng], 18);
}

function displayNavigationPath(path) {
    clearPath();

    if (path.length < 2) {
        showStatus('Path too short', 'error');
        return;
    }

    const coordinates = path.map(hop => [hop.latitude, hop.longitude]);

    navigationPath = L.polyline(coordinates, {
        color: '#ff0000',
        weight: 6,
        opacity: 0.9,
        dashArray: '10, 10'
    }).addTo(map);

    path.forEach((hop, index) => {
        if (hop.type === 'hop' || (index > 0 && index < path.length - 1 && hop.type !== 'marker')) {
            const marker = L.circleMarker([hop.latitude, hop.longitude], {
                radius: 6,
                fillColor: '#ffff00',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);

            marker.bindPopup(`<b>Waypoint:</b> ${hop.name || 'Connection Point'}`);
            pathMarkers.push(marker);
        }
    });

    if (path.length > 0) {
        const startMarker = L.circleMarker([path[0].latitude, path[0].longitude], {
            radius: 10,
            fillColor: '#00ff00',
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map);
        startMarker.bindPopup(`<b>🚀 Start:</b> ${path[0].name || 'Starting Point'}`);
        pathMarkers.push(startMarker);

        const endMarker = L.circleMarker([path[path.length - 1].latitude, path[path.length - 1].longitude], {
            radius: 10,
            fillColor: '#ff0000',
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map);
        endMarker.bindPopup(`<b>🎯 Destination:</b> ${path[path.length - 1].name || 'Destination'}`);
        pathMarkers.push(endMarker);
    }

    map.fitBounds(navigationPath.getBounds().pad(0.1));
}

function showRouteInfo(startHop, endHop, path, fromCurrent = false) {
    const container = document.getElementById('selectedMarkers');
    const routeInfo = document.getElementById('routeInfo');

    if (!container || !routeInfo) return;

    let info = '<div style="border-left: 4px solid #007bff; padding-left: 12px;">';
    info += '<strong style="color: #007bff;">🗺️ Route Information:</strong><br>';

    if (fromCurrent) {
        info += '<span style="color: #28a745;">📍 Start:</span> Your Current Location<br>';
    } else if (startHop) {
        info += `<span style="color: #28a745;">📍 Start:</span> ${startHop.name}<br>`;
    }

    info += `<span style="color: #dc3545;">🎯 Destination:</span> ${endHop.name}<br>`;
    info += `<span style="color: #6c757d;">📊 Route:</span> ${path.length} waypoint${path.length > 1 ? 's' : ''}<br>`;

    let totalDistance = 0;
    for (let i = 0; i < path.length - 1; i++) {
        const dist = calculateDistance(
            path[i].latitude, path[i].longitude,
            path[i + 1].latitude, path[i + 1].longitude
        );
        totalDistance += dist;
    }

    info += `<span style="color: #17a2b8;">📏 Distance:</span> ~${Math.round(totalDistance)}m<br>`;
    info += `<span style="color: #ffc107;">⏱️ Walking Time:</span> ~${Math.round(totalDistance / 80)} min`;
    info += '</div>';

    routeInfo.innerHTML = info;
    container.style.display = 'block';
}

function calculateDistance(lat1, lng1, lat2, lng2) {
    const earthRadius = 6371000;
    const lat1Rad = lat1 * Math.PI / 180;
    const lat2Rad = lat2 * Math.PI / 180;
    const deltaLat = (lat2 - lat1) * Math.PI / 180;
    const deltaLng = (lng2 - lng1) * Math.PI / 180;

    const a = Math.sin(deltaLat/2) * Math.sin(deltaLat/2) +
             Math.cos(lat1Rad) * Math.cos(lat2Rad) *
             Math.sin(deltaLng/2) * Math.sin(deltaLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return earthRadius * c;
}

function clearNavigation() {
    clearPath();

    if (currentLocationMarker) {
        map.removeLayer(currentLocationMarker);
        currentLocationMarker = null;
    }

    markers.forEach(marker => {
        if (marker._icon) {
            marker._icon.style.filter = '';
            marker._icon.style.transform = '';
        }
    });

    const startSelect = document.getElementById('startMarkerSelect');
    const endSelect = document.getElementById('endMarkerSelect');
    const currentDestSelect = document.getElementById('currentDestinationSelect');
    const navigateBtn = document.getElementById('navigateBtn');
    const currentNavBtn = document.getElementById('currentNavigateBtn');

    if (startSelect) startSelect.value = '';
    if (endSelect) endSelect.value = '';
    if (currentDestSelect) currentDestSelect.value = '';
    if (navigateBtn) navigateBtn.disabled = true;
    if (currentNavBtn) currentNavBtn.disabled = true;

    hideNavigationMode();

    const container = document.getElementById('selectedMarkers');
    if (container) container.style.display = 'none';
}

function clearPath() {
    if (navigationPath) {
        map.removeLayer(navigationPath);
        navigationPath = null;
    }

    pathMarkers.forEach(marker => map.removeLayer(marker));
    pathMarkers = [];
}

function showNavigationMode(title, description) {
    const navMode = document.getElementById('navigationMode');
    const modeTitle = document.getElementById('modeTitle');
    const modeDescription = document.getElementById('modeDescription');

    if (navMode) navMode.style.display = 'block';
    if (modeTitle) modeTitle.innerHTML = `<span class="loading-spinner"></span> ${title}`;
    if (modeDescription) modeDescription.textContent = description;
}

function hideNavigationMode() {
    const navMode = document.getElementById('navigationMode');
    if (navMode) navMode.style.display = 'none';
}

function showMarkerDetails(markerId) {
    const marker = markers.get(markerId);
    if (!marker || !marker.markerData) return;

    const markerData = marker.markerData;

    const modalHTML = `
        <div id="markerModal" class="marker-modal" onclick="closeMarkerModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3>${markerData.name}</h3>
                    <button class="modal-close" onclick="closeMarkerModal()">&times;</button>
                </div>
                <div class="modal-body">
                    ${markerData.image_path ?
                        `<div class="modal-image-container">
                            <img src="/storage/${markerData.image_path}" alt="${markerData.name}" class="modal-image">
                        </div>` :
                        ''
                    }
                    ${markerData.description ?
                        `<div class="modal-description">
                            <h4>Description:</h4>
                            <p>${markerData.description}</p>
                        </div>` :
                        '<p>No description available.</p>'
                    }
                    <div class="modal-coordinates">
                        <small><strong>Location:</strong> ${markerData.latitude.toFixed(6)}, ${markerData.longitude.toFixed(6)}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeMarkerModal()">Close</button>
                    <button class="btn btn-success" onclick="setAsDestination(${markerData.id}); closeMarkerModal();">Navigate Here</button>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('markerModal');
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    setTimeout(() => {
        document.getElementById('markerModal').classList.add('show');
    }, 10);
}

function closeMarkerModal(event) {
    if (event && event.target.classList.contains('modal-content')) return;

    const modal = document.getElementById('markerModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

function showSelectedMarkerDetails() {
    const markerId = document.getElementById('detailsMarkerSelect')?.value;
    if (markerId) {
        showMarkerDetails(parseInt(markerId));
    }
}

function showStatus(message, type = 'info') {
    console.log(`Status (${type}):`, message);

    let statusEl = document.getElementById('statusMessage');
    if (!statusEl) {
        statusEl = document.createElement('div');
        statusEl.id = 'statusMessage';
        statusEl.className = 'status-message';
        document.body.appendChild(statusEl);
    }

    statusEl.className = `status-message ${type}`;
    statusEl.textContent = message;
    statusEl.style.display = 'block';

    const delay = type === 'error' ? 7000 : 4000;
    setTimeout(() => {
        if (statusEl.style.display === 'block') {
            statusEl.style.display = 'none';
        }
    }, delay);
}

function initializeUserMap(locationCode) {
    initMap(locationCode);
}

function showSelectedMarkerDetails() {
    const markerId = document.getElementById('detailsMarkerSelect')?.value;
    if (markerId) {
        showMarkerDetails(parseInt(markerId));
    }
}

// Show all markers in a browseable modal
function showAllMarkersModal() {
    const allMarkers = Array.from(markers.values());

    if (allMarkers.length === 0) {
        alert('No markers available');
        return;
    }

    let modalHTML = `
        <div id="allMarkersModal" class="marker-modal" onclick="closeAllMarkersModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 700px;">
                <div class="modal-header">
                    <h3>Browse All Markers (${allMarkers.length})</h3>
                    <button class="modal-close" onclick="closeAllMarkersModal()">&times;</button>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <div class="markers-grid">
    `;

    allMarkers.forEach(marker => {
        const data = marker.markerData;
        modalHTML += `
            <div class="marker-card">
                <div class="marker-card-header">
                    <h4>${data.name}</h4>
                    <button class="btn btn-sm btn-success" onclick="setAsDestination(${data.id}); closeAllMarkersModal();">🎯 Navigate</button>
                </div>
                ${data.image_path ?
                    `<div class="marker-card-image">
                        <img src="/storage/${data.image_path}" alt="${data.name}">
                    </div>` : ''
                }
                <div class="marker-card-content">
                    ${data.description ? `<p>${data.description}</p>` : '<p><em>No description available</em></p>'}
                    <small>📍 ${data.latitude.toFixed(6)}, ${data.longitude.toFixed(6)}</small>
                </div>
                <div class="marker-card-actions">
                    <button class="btn btn-info btn-sm" onclick="showMarkerDetails(${data.id}); closeAllMarkersModal();">👁️ Details</button>
                    <button class="btn btn-warning btn-sm" onclick="setAsStart(${data.id}); closeAllMarkersModal();">📍 Set Start</button>
                </div>
            </div>
        `;
    });

    modalHTML += `
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeAllMarkersModal()">Close</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    setTimeout(() => document.getElementById('allMarkersModal').classList.add('show'), 10);
}

function closeAllMarkersModal(event) {
    if (event && event.target.classList.contains('modal-content')) return;

    const modal = document.getElementById('allMarkersModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

// Make functions globally available
window.showSelectedMarkerDetails = showSelectedMarkerDetails;
window.showAllMarkersModal = showAllMarkersModal;
window.closeAllMarkersModal = closeAllMarkersModal;

// Make functions globally available
window.showMarkerDetails = showMarkerDetails;
window.closeMarkerModal = closeMarkerModal;
window.setAsStart = setAsStart;
window.setAsDestination = setAsDestination;
window.navigateMarkerToMarker = navigateMarkerToMarker;
window.navigateFromCurrent = navigateFromCurrent;
window.clearNavigation = clearNavigation;
window.showSelectedMarkerDetails = showSelectedMarkerDetails;
window.showAllMarkersModal = showAllMarkersModal;
window.closeAllMarkersModal = closeAllMarkersModal;
