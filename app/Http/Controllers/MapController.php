<?php

namespace App\Http\Controllers;

use App\Models\Hop;
use App\Models\RoadConnection;
use App\Models\CampusBoundary;
use App\Models\Overlay;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MapController extends Controller
{
    public function index($locationCode = 'main-campus')
    {
        $location = Location::where('code', $locationCode)->firstOrFail();
        $locations = Location::where('active', true)->orderBy('name')->get();

        return view('map.index', compact('location', 'locations'));
    }

    public function getMapData($locationCode = 'main-campus')
    {
        try {
            $location = Location::where('code', $locationCode)->firstOrFail();

            $hops = Hop::with(['connectionsFrom.hopTo', 'connectionsTo.hopFrom'])
                       ->where('location_code', $locationCode)
                       ->orderBy('created_at', 'desc')
                       ->get();

            $connections = RoadConnection::with(['hopFrom', 'hopTo'])
                                        ->where('location_code', $locationCode)
                                        ->get();

            $boundary = CampusBoundary::where('location_code', $locationCode)->first();

            $overlays = Overlay::where('active', true)
                              ->where('location_code', $locationCode)
                              ->orderBy('created_at', 'desc')
                              ->get();

            return response()->json([
                'hops' => $hops,
                'connections' => $connections,
                'boundary' => $boundary,
                'overlays' => $overlays,
                'location' => $location
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading map data', ['error' => $e->getMessage(), 'location' => $locationCode]);
            return response()->json([
                'error' => 'Failed to load map data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function saveHop(Request $request, $locationCode = 'main-campus')
    {
        try {
            Location::where('code', $locationCode)->firstOrFail();

            $validated = $request->validate([
                'type' => 'required|in:marker,hop',
                'name' => 'required_if:type,marker|nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'icon' => 'nullable|string|max:50',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            $validated['location_code'] = $locationCode;

            if ($validated['type'] === 'hop' && empty($validated['name'])) {
                $hopCount = Hop::where('location_code', $locationCode)
                              ->where('type', 'hop')
                              ->count();
                $validated['name'] = "Hop " . ($hopCount + 1);
            }

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();
                $path = $image->storeAs('markers/' . $locationCode, $filename, 'public');
                $validated['image_path'] = $path;
            }

            $hop = Hop::create($validated);
            $hop->load(['connectionsFrom.hopTo', 'connectionsTo.hopFrom']);

            return response()->json([
                'success' => true,
                'hop' => $hop,
                'message' => ucfirst($validated['type']) . ' created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error saving hop', ['error' => $e->getMessage(), 'location' => $locationCode]);
            return response()->json([
                'error' => 'Failed to save hop',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function connectHops(Request $request, $locationCode = 'main-campus')
    {
        try {
            Location::where('code', $locationCode)->firstOrFail();

            $validated = $request->validate([
                'hop_from_id' => 'required|exists:hops,id',
                'hop_to_id' => 'required|exists:hops,id|different:hop_from_id',
                'name' => 'nullable|string|max:255',
                'width' => 'nullable|integer|min:1|max:20',
                'color' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
            ]);

            $validated['location_code'] = $locationCode;

            $hopFrom = Hop::where('id', $validated['hop_from_id'])
                         ->where('location_code', $locationCode)
                         ->first();

            $hopTo = Hop::where('id', $validated['hop_to_id'])
                       ->where('location_code', $locationCode)
                       ->first();

            if (!$hopFrom || !$hopTo) {
                return response()->json([
                    'error' => 'Both hops must belong to the same location'
                ], 422);
            }

            $existingConnection = RoadConnection::where('location_code', $locationCode)
                ->where(function($query) use ($validated) {
                    $query->where(function($q) use ($validated) {
                        $q->where('hop_from_id', $validated['hop_from_id'])
                          ->where('hop_to_id', $validated['hop_to_id']);
                    })->orWhere(function($q) use ($validated) {
                        $q->where('hop_from_id', $validated['hop_to_id'])
                          ->where('hop_to_id', $validated['hop_from_id']);
                    });
                })->first();

            if ($existingConnection) {
                return response()->json([
                    'error' => 'Connection already exists between these hops'
                ], 422);
            }

            $validated['width'] = $validated['width'] ?? 5;
            $validated['color'] = $validated['color'] ?? '#0066cc';
            $validated['name'] = $validated['name'] ?? ($hopFrom->name . ' - ' . $hopTo->name);

            $connection = RoadConnection::create($validated);
            $connection->distance = $connection->calculateDistance();
            $connection->save();

            $connection->load(['hopFrom', 'hopTo']);

            return response()->json([
                'success' => true,
                'connection' => $connection,
                'message' => 'Connection created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating connection', ['error' => $e->getMessage(), 'location' => $locationCode]);
            return response()->json([
                'error' => 'Failed to create connection',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function findPath(Request $request, $locationCode = 'main-campus')
    {
        try {
            Location::where('code', $locationCode)->firstOrFail();
            $validated = $request->validate([
                'start_id' => 'nullable|exists:hops,id',
                'end_id' => 'required|exists:hops,id',
                'current_lat' => 'nullable|numeric|between:-90,90',
                'current_lng' => 'nullable|numeric|between:-180,180',
            ]);

            $endHop = Hop::where('id', $validated['end_id'])
                         ->where('location_code', $locationCode)
                         ->first();
            if (!$endHop) {
                Log::warning('End hop not found', ['end_id' => $validated['end_id'], 'location' => $locationCode]);
                return response()->json([
                    'error' => 'Destination not found in this location'
                ], 404);
            }

            if (isset($validated['start_id']) && $validated['start_id']) {
                $startHop = Hop::where('id', $validated['start_id'])
                              ->where('location_code', $locationCode)
                              ->first();
                if (!$startHop) {
                    return response()->json([
                        'error' => 'Start location not found in this location'
                    ], 404);
                }
            } else {
                if (!isset($validated['current_lat']) || !isset($validated['current_lng'])) {
                    return response()->json([
                        'error' => 'Current location coordinates required when no start location specified'
                    ], 422);
                }

                $startHop = $this->findNearestHop(
                    $validated['current_lat'],
                    $validated['current_lng'],
                    $locationCode
                );

                if (!$startHop) {
                    return response()->json([
                        'error' => 'No accessible starting point found near your location'
                    ], 404);
                }
            }

            $connectionCount = RoadConnection::where('location_code', $locationCode)->count();
            Log::info('Connection count for location', ['location' => $locationCode, 'count' => $connectionCount]);


            if ($connectionCount === 0) {
                return response()->json([
                    'error' => 'No road connections found for this location. Please add some roads first.'
                ], 404);
            }

            $path = $this->dijkstraPath($startHop, $endHop, $locationCode);

            Log::info('Path found', ['path_length' => count($path), 'start' => $startHop->name ?? 'Unknown', 'end' => $endHop->name ?? 'Unknown']);

            if (empty($path)) {
                return response()->json([
                    'error' => 'No path found between the selected points. Make sure they are connected by roads.'
                ], 404);
            }

            $totalDistance = 0;
            for ($i = 0; $i < count($path) - 1; $i++) {
                $segmentDistance = $this->calculateDistance(
                    $path[$i]->latitude, $path[$i]->longitude,
                    $path[$i + 1]->latitude, $path[$i + 1]->longitude
                );
                $totalDistance += $segmentDistance;
            }
            $estimatedTime = round($totalDistance / 83.33, 1);

            return response()->json([
                'success' => true,
                'path' => $path,
                'start_hop' => $startHop,
                'end_hop' => $endHop,
                'total_distance' => round($totalDistance, 2),
                'estimated_time' => max($estimatedTime, 1),
                'waypoint_count' => count($path)
            ]);

        } catch (ValidationException $e) {
            Log::error('Validation error in findPath', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in findPath', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Failed to find path',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function saveBoundary(Request $request, $locationCode = 'main-campus')
    {
        try {
            Location::where('code', $locationCode)->firstOrFail();

            $validated = $request->validate([
                'north' => 'required|numeric|between:-90,90',
                'south' => 'required|numeric|between:-90,90',
                'east' => 'required|numeric|between:-180,180',
                'west' => 'required|numeric|between:-180,180',
            ]);

            if ($validated['north'] <= $validated['south']) {
                return response()->json([
                    'error' => 'North boundary must be greater than south boundary'
                ], 422);
            }

            if ($validated['east'] <= $validated['west']) {
                return response()->json([
                    'error' => 'East boundary must be greater than west boundary'
                ], 422);
            }

            $validated['location_code'] = $locationCode;

            CampusBoundary::where('location_code', $locationCode)->delete();
            $boundary = CampusBoundary::create($validated);

            return response()->json([
                'success' => true,
                'boundary' => $boundary,
                'message' => 'Boundary saved successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error saving boundary', ['error' => $e->getMessage(), 'location' => $locationCode]);
            return response()->json([
                'error' => 'Failed to save boundary',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadOverlay(Request $request, $locationCode = 'main-campus')
    {
        try {
            Location::where('code', $locationCode)->firstOrFail();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'corners' => 'required|json',
                'opacity' => 'nullable|numeric|min:0|max:1',
            ]);

            $corners = json_decode($validated['corners'], true);
            if (!$corners || count($corners) !== 4) {
                return response()->json([
                    'error' => 'Invalid corners data - must have exactly 4 corner points'
                ], 422);
            }

            foreach ($corners as $index => $corner) {
                if (!isset($corner[0]) || !isset($corner[1]) ||
                    !is_numeric($corner[0]) || !is_numeric($corner[1])) {
                    return response()->json([
                        'error' => "Invalid corner #{$index} - must have latitude and longitude"
                    ], 422);
                }
            }

            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $path = $image->storeAs('overlays/' . $locationCode, $filename, 'public');

            $overlay = Overlay::create([
                'name' => $validated['name'],
                'image_path' => $path,
                'corners' => $corners,
                'opacity' => $validated['opacity'] ?? 0.7,
                'location_code' => $locationCode,
                'active' => true
            ]);

            return response()->json([
                'success' => true,
                'overlay' => $overlay,
                'message' => 'Overlay uploaded successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading overlay', ['error' => $e->getMessage(), 'location' => $locationCode]);
            return response()->json([
                'error' => 'Failed to upload overlay',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteHop($locationCode, Hop $hop)
    {
        try {
            if ($hop->location_code !== $locationCode) {
                return response()->json([
                    'error' => 'Hop not found in this location'
                ], 404);
            }

            if ($hop->image_path && Storage::disk('public')->exists($hop->image_path)) {
                Storage::disk('public')->delete($hop->image_path);
            }

            RoadConnection::where(function($query) use ($hop) {
                $query->where('hop_from_id', $hop->id)
                      ->orWhere('hop_to_id', $hop->id);
            })->delete();

            $hop->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hop deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting hop', ['error' => $e->getMessage(), 'hop_id' => $hop->id ?? 'unknown']);
            return response()->json([
                'error' => 'Failed to delete hop',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteConnection($locationCode, RoadConnection $connection)
    {
        try {
            if ($connection->location_code !== $locationCode) {
                return response()->json([
                    'error' => 'Connection not found in this location'
                ], 404);
            }

            $connection->delete();

            return response()->json([
                'success' => true,
                'message' => 'Connection deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting connection', ['error' => $e->getMessage(), 'connection_id' => $connection->id ?? 'unknown']);
            return response()->json([
                'error' => 'Failed to delete connection',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function findNearestHop($lat, $lng, $locationCode)
    {
        try {
            $hops = Hop::where('location_code', $locationCode)->get();
            $nearest = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($hops as $hop) {
                $distance = $this->calculateDistance($lat, $lng, $hop->latitude, $hop->longitude);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearest = $hop;
                }
            }

            return $nearest;
        } catch (\Exception $e) {
            Log::error('Error finding nearest hop', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000;
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lng2 - $lng1);
        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon/2) * sin($deltaLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    private function dijkstraPath($start, $end, $locationCode)
    {
        try {
            $distances = [];
            $previous = [];
            $unvisited = [];

            $hops = Hop::where('location_code', $locationCode)->get();

            if ($hops->isEmpty()) {
                Log::warning('No hops found for location', ['location' => $locationCode]);
                return [];
            }

            foreach ($hops as $hop) {
                $distances[$hop->id] = PHP_FLOAT_MAX;
                $previous[$hop->id] = null;
                $unvisited[$hop->id] = $hop;
            }

            $distances[$start->id] = 0;

            while (!empty($unvisited)) {
                $current = null;
                $minDistance = PHP_FLOAT_MAX;

                foreach ($unvisited as $id => $hop) {
                    if ($distances[$id] < $minDistance) {
                        $minDistance = $distances[$id];
                        $current = $hop;
                    }
                }

                if ($current === null || $minDistance === PHP_FLOAT_MAX) {
                    Log::warning('No reachable nodes found in dijkstra');
                    break;
                }

                if ($current->id == $end->id) {
                    break;
                }

                unset($unvisited[$current->id]);

                $connections = RoadConnection::where('location_code', $locationCode)
                                            ->where(function($query) use ($current) {
                                                $query->where('hop_from_id', $current->id)
                                                      ->orWhere('hop_to_id', $current->id);
                                            })
                                            ->get();

                foreach ($connections as $connection) {
                    $neighborId = $connection->hop_from_id == $current->id
                        ? $connection->hop_to_id
                        : $connection->hop_from_id;

                    if (!isset($unvisited[$neighborId])) {
                        continue;
                    }

                    $connectionDistance = $connection->distance ?? 1;
                    $alt = $distances[$current->id] + $connectionDistance;

                    if ($alt < $distances[$neighborId]) {
                        $distances[$neighborId] = $alt;
                        $previous[$neighborId] = $current->id;
                    }
                }
            }

            // Reconstruct path
            $path = [];
            $currentId = $end->id;

            if ($previous[$currentId] === null && $currentId !== $start->id) {
                Log::warning('No path found between hops', ['start' => $start->id, 'end' => $end->id]);
                return [];
            }

            while ($currentId !== null) {
                $hop = $hops->firstWhere('id', $currentId);
                if ($hop) {
                    array_unshift($path, $hop);
                }
                $currentId = $previous[$currentId] ?? null;
            }

            return $path;

        } catch (\Exception $e) {
            Log::error('Error in dijkstraPath', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return [];
        }
    }
}
