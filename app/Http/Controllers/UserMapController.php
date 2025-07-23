<?php

namespace App\Http\Controllers;

use App\Models\Hop;
use App\Models\RoadConnection;
use App\Models\CampusBoundary;
use App\Models\Overlay;
use Illuminate\Http\Request;

class UserMapController extends Controller
{
    public function index()
    {
        return view('map.user');
    }

    public function getMapData()
    {
        $hops = Hop::with(['connectionsFrom.hopTo', 'connectionsTo.hopFrom'])->get();

        return response()->json([
            'hops' => $hops,
            'connections' => RoadConnection::with(['hopFrom', 'hopTo'])->get(),
            'boundary' => CampusBoundary::first(),
            'overlays' => Overlay::where('active', true)->get(),
        ]);
    }

    public function findPath(Request $request)
    {
        $validated = $request->validate([
            'start_id' => 'nullable|exists:hops,id',
            'end_id' => 'required|exists:hops,id',
            'current_lat' => 'nullable|numeric',
            'current_lng' => 'nullable|numeric',
        ]);

        if ($validated['start_id']) {
            $startHop = Hop::find($validated['start_id']);
        } else {
            // Find nearest hop to current position
            $startHop = $this->findNearestHop($validated['current_lat'], $validated['current_lng']);
        }

        $endHop = Hop::find($validated['end_id']);

        $path = $this->dijkstraPath($startHop, $endHop);

        return response()->json([
            'path' => $path,
            'start_hop' => $startHop,
            'end_hop' => $endHop,
        ]);
    }

    private function findNearestHop($lat, $lng)
    {
        $hops = Hop::all();
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
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // meters
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

    private function dijkstraPath($start, $end)
    {
        $distances = [];
        $previous = [];
        $unvisited = [];

        $hops = Hop::all();

        foreach ($hops as $hop) {
            $distances[$hop->id] = PHP_FLOAT_MAX;
            $previous[$hop->id] = null;
            $unvisited[$hop->id] = true;
        }

        $distances[$start->id] = 0;

        while (count($unvisited) > 0) {
            // Find unvisited node with minimum distance
            $current = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($unvisited as $id => $value) {
                if ($distances[$id] < $minDistance) {
                    $minDistance = $distances[$id];
                    $current = $id;
                }
            }

            if ($current === null || $current == $end->id) {
                break;
            }

            unset($unvisited[$current]);

            $currentHop = Hop::find($current);
            $connections = RoadConnection::where('hop_from_id', $current)
                                        ->orWhere('hop_to_id', $current)
                                        ->get();

            foreach ($connections as $connection) {
                $neighborId = $connection->hop_from_id == $current
                    ? $connection->hop_to_id
                    : $connection->hop_from_id;

                if (!isset($unvisited[$neighborId])) {
                    continue;
                }

                $alt = $distances[$current] + $connection->distance;

                if ($alt < $distances[$neighborId]) {
                    $distances[$neighborId] = $alt;
                    $previous[$neighborId] = $current;
                }
            }
        }

        // Reconstruct path
        $path = [];
        $current = $end->id;

        while ($current !== null) {
            array_unshift($path, Hop::find($current));
            $current = $previous[$current];
        }

        return $path;
    }
}
