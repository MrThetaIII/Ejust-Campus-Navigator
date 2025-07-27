@extends('layouts.main')

@section('title', 'Campus Navigator')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user-map.css') }}">
@endpush

@section('content')
    <div id="map"></div>

    <div class="navigation-panel">
        <h2>🧭 Campus Navigation</h2>

        <div id="navigationMode" class="navigation-mode" style="display: none;">
            <h3 id="modeTitle">Navigation Mode</h3>
            <p id="modeDescription">Select your destination</p>
        </div>

        <!-- Marker to Marker Navigation -->
        <div class="nav-section">
            <h3>📍 Navigate Between Locations</h3>
            <div class="dropdown-section">
                <div class="dropdown-group">
                    <label class="dropdown-label" for="startMarkerSelect">From:</label>
                    <select id="startMarkerSelect" class="marker-dropdown">
                        <option value="">-- Select Start Location --</option>
                    </select>
                </div>
                <div class="dropdown-group">
                    <label class="dropdown-label" for="endMarkerSelect">To:</label>
                    <select id="endMarkerSelect" class="marker-dropdown">
                        <option value="">-- Select Destination --</option>
                    </select>
                </div>
                <button class="nav-button navigate" onclick="navigateMarkerToMarker()" id="navigateBtn" disabled>
                    🧭 Find Route
                </button>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Current Location Navigation -->
        <div class="nav-section">
            <h3>📱 Navigate from Current Location</h3>
            <div class="dropdown-section">
                <div class="dropdown-group">
                    <label class="dropdown-label" for="currentDestinationSelect">Destination:</label>
                    <select id="currentDestinationSelect" class="marker-dropdown">
                        <option value="">-- Select Destination --</option>
                    </select>
                </div>
                <button class="nav-button current-location" onclick="navigateFromCurrent()" id="currentNavigateBtn" disabled>
                    📍 Navigate from My Location
                </button>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Marker Details Section -->
        <div class="nav-section">
            <h3>📋 Marker Information</h3>
            <div class="dropdown-section">
                <div class="dropdown-group">
                    <label class="dropdown-label" for="detailsMarkerSelect">Select Marker:</label>
                    <select id="detailsMarkerSelect" class="marker-dropdown">
                        <option value="">-- Select Marker to View --</option>
                    </select>
                </div>
                <button class="nav-button" onclick="showSelectedMarkerDetails()" id="showDetailsBtn" disabled>
                    👁️ View Details
                </button>
                <button class="nav-button btn-info" onclick="showAllMarkersModal()">
                    📋 Browse All Markers
                </button>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Quick Actions -->
        <div class="nav-section">
            <button class="nav-button clear" onclick="clearNavigation()">
                ✖️ Clear Navigation
            </button>
        </div>

        <div id="selectedMarkers" class="marker-info" style="display: none;">
            <div id="routeInfo"></div>
        </div>
    </div>

    <div class="coordinates-display" id="coordinatesDisplay">
        Lat: 0.000000, Lng: 0.000000
    </div>

    <div class="legend">
        <h4>🗺️ Legend</h4>
        <div class="legend-item">
            <div class="legend-icon legend-marker"></div>
            <span>Location Marker</span>
        </div>
        <div class="legend-item">
            <div class="legend-icon legend-road"></div>
            <span>Walking Path</span>
        </div>
        <div class="legend-item">
            <div class="legend-icon legend-path"></div>
            <span>Navigation Route</span>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/user-map.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeUserMap('{{ $location->code }}');
        });
    </script>
@endpush
