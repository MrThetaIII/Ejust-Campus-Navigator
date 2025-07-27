<?php

namespace App\Http\Controllers;

use App\Models\Hop;
use App\Models\RoadConnection;
use App\Models\CampusBoundary;
use App\Models\Overlay;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserMapController extends Controller
{
    public function index($locationCode = 'main-campus')
    {
        $location = Location::where('code', $locationCode)->firstOrFail();
        $locations = Location::where('active', true)->orderBy('name')->get();

        return view('map.user', compact('location', 'locations'));
    }

    public function getMapData($locationCode = 'main-campus')
    {
        try {
            $location = Location::where('code', $locationCode)->firstOrFail();

            $hops = Hop::with(['connectionsFrom.hopTo', 'connectionsTo.hopFrom'])
                       ->where('location_code', $locationCode)
                       ->orderBy('type', 'desc')
                       ->orderBy('name')
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
            Log::error('Error loading user map data', ['error' => $e->getMessage(), 'location' => $locationCode]);
            return response()->json([
                'error' => 'Failed to load map data',
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

            Log::info('User FindPath request', [
                'location' => $locationCode,
                'validated' => $validated
            ]);

            $endHop = Hop::where('id', $validated['end_id'])
                         ->where('location_code', $locationCode)
                         ->first();

            if (!$endHop) {
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

                if ($startHop->id === $endHop->id) {
                    return response()->json([
                        'error' => 'Start and destination cannot be the same'
                    ], 422);
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
            if ($connectionCount === 0) {
                return response()->json([
                    'error' => 'No road connections found for this location'
                ], 404);
            }

            $path = $this->dijkstraPath($startHop, $endHop, $locationCode);

            if (empty($path)) {
                return response()->json([
                    'error' => 'No path found between the selected points'
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
            Log::error('User validation error in findPath', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('User error in findPath', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Failed to find path',
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
            Log::error('Error finding nearest hop for user', ['error' => $e->getMessage()]);
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
            Log::error('Error in user dijkstraPath', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
