<?php

namespace App\Support;

use App\Models\Need;

/**
 * Turns need rows into the plain array the map, the list and the recap render.
 *
 * Both the map (client JSON) and the Blade pages read this shape, so the field
 * names live here once: `id`, `title`, `marker_label`, `category`, `location`,
 * `area`, `distance_km`, `supporters`, `hours_ago`, `description`, `latitude`,
 * `longitude`, `opportunity`.
 */
final class NeedPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Need $need): array
    {
        $createdAt = $need->created_at;

        return [
            'id' => (string) $need->id,
            'title' => (string) $need->title,
            'marker_label' => (string) ($need->marker_label ?: $need->title),
            'category' => (string) $need->category,
            'location' => (string) ($need->location ?? ''),
            'area' => (string) ($need->area ?? ''),
            'distance_km' => (float) ($need->distance_km ?? 0),
            'supporters' => (int) $need->supporters,
            'hours_ago' => $createdAt !== null ? max(0, (int) $createdAt->diffInHours(now())) : 0,
            'description' => (string) ($need->description ?? ''),
            'latitude' => $need->latitude !== null ? (float) $need->latitude : null,
            'longitude' => $need->longitude !== null ? (float) $need->longitude : null,
            'opportunity' => (bool) $need->opportunity,
        ];
    }

    /**
     * Newest first, which is also the order the list and the recap use.
     *
     * @param  iterable<int, Need>  $needs
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $needs): array
    {
        $rows = [];

        foreach ($needs as $need) {
            $rows[] = self::toArray($need);
        }

        return $rows;
    }
}
