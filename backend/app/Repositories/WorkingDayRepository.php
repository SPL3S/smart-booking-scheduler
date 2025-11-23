<?php

namespace App\Repositories;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Foundation\Application;

class WorkingDayRepository
{
    protected $translator;
    protected $app;

    public function __construct(Translator $translator, Application $app)
    {
        $this->translator = $translator;
        $this->app = $app;
    }

    /**
     * Get the full day name for a given day index.
     *
     * @param int $dayIndex Day index (0=Sunday through 6=Saturday)
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return string
     */
    public function getDayName(int $dayIndex, ?string $locale = null): string
    {
        if (!$this->isValidDayIndex($dayIndex)) {
            $dayIndex = 0;
        }

        $locale = $locale ?? $this->app->getLocale();
        $key = "days.weekdays.{$dayIndex}";

        $translation = $this->translator->get($key, [], $locale);

        // Fallback to default locale if translation not found
        if ($translation === $key) {
            $translation = $this->translator->get($key, [], $this->app->getFallbackLocale());
        }

        return $translation;
    }

    /**
     * Get the short day name for a given day index.
     *
     * @param int $dayIndex Day index (0=Sunday through 6=Saturday)
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return string
     */
    public function getDayNameShort(int $dayIndex, ?string $locale = null): string
    {
        if (!$this->isValidDayIndex($dayIndex)) {
            $dayIndex = 0;
        }

        $locale = $locale ?? $this->app->getLocale();
        $key = "days.weekdays_short.{$dayIndex}";

        $translation = $this->translator->get($key, [], $locale);

        // Fallback to default locale if translation not found
        if ($translation === $key) {
            $translation = $this->translator->get($key, [], $this->app->getFallbackLocale());
        }

        return $translation;
    }

    /**
     * Get all day names.
     *
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return array
     */
    public function getAllDayNames(?string $locale = null): array
    {
        $days = [];
        $currentLocale = $locale ?? $this->app->getLocale();

        for ($i = 0; $i <= 6; $i++) {
            $days[$i] = $this->getDayName($i, $currentLocale);
        }

        return $days;
    }

    /**
     * Get all short day names.
     *
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return array
     */
    public function getAllDayNamesShort(?string $locale = null): array
    {
        $days = [];
        $currentLocale = $locale ?? $this->app->getLocale();

        for ($i = 0; $i <= 6; $i++) {
            $days[$i] = $this->getDayNameShort($i, $currentLocale);
        }

        return $days;
    }

    /**
     * Check if the given day index is a weekend (Saturday or Sunday).
     *
     * @param int $dayIndex Day index (0=Sunday through 6=Saturday)
     * @return bool
     */
    public function isWeekend(int $dayIndex): bool
    {
        return $dayIndex === 0 || $dayIndex === 6;
    }

    /**
     * Validate if the day index is within valid range (0-6).
     *
     * @param int $dayIndex Day index
     * @return bool
     */
    public function isValidDayIndex(int $dayIndex): bool
    {
        return $dayIndex >= 0 && $dayIndex <= 6;
    }
}
