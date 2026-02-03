<?php
/**
 * Get the full name of a user
 */
if (!function_exists('full_name')) {
    function full_name($user): string
    {
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? 'Unknown');
    }
}

/**
 * Get the groups a user belongs to as a comma-separated string
 */
if (!function_exists('user_groups')) {
    function user_groups($user): string
    {
        return $user->groups->pluck('name')->implode(', ');
    }
}

/**
 * Check if a user has a specific permission
 */
if (!function_exists('has_permission')) {
	function has_permission($user, $permission): bool
	{
		return $user->hasPermissionTo($permission);
	}
}

/**
 * Check if a user belongs to a specific group
 */
if (!function_exists('in_group')) {
	function in_group($user, $group): bool
	{
		return $user->groups->contains('name', $group);
	}
}

/**
 * Truncate the given text to a specified length with ellipsis at the end
 */
if (!function_exists('truncate_text')) {
    function truncate_text(string $text, int $maxLength = 100): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, 0, $maxLength - 3) . '...';
    }
}


/**
 * Truncate the given text/filename to a specified length with ellipsis in the middle
 */
if (!function_exists('truncate_filename')) {
    function truncate_filename(string $filename, int $maxLength = 100): string
    {
        if (strlen($filename) <= $maxLength) {
            return $filename;
        }

        $halfLength = (int)($maxLength / 2);
        return substr($filename, 0, $halfLength) . '...' . substr($filename, -$halfLength);
    }
}

/**
 * Get countries list from the countries.json file
 */
if (!function_exists('get_countries')) {
    /**
     * Get the list of countries from the JSON file.
     *
     * @return array
     */
    function get_countries(): array
    {
        $json = file_get_contents('vendor/countries.json');
        return json_decode($json, true);
    }
}