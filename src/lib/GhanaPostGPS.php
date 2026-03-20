<?php

namespace App\Lib;

class GhanaPostGPS {
    /**
     * Validates a GhanaPostGPS address format (e.g., AK-485-9323)
     */
    public static function validate($address) {
        // Regex for Ghana Post GPS: 2 letters, hyphen, 3-4 digits, hyphen, 3-4 digits
        return preg_match('/^[A-Z]{2}-\d{3,4}-\d{3,4}$/', strtoupper($address));
    }

    /**
     * Mock lookup to simulate coordinate retrieval
     */
    public static function getCoords($address) {
        if (!self::validate($address)) return null;
        
        // In a real app, this would call the GhanaPostGPS API
        // Returning Accra default for demo
        return ['lat' => 5.6037, 'lng' => -0.1870];
    }
}
