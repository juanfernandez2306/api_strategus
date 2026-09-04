<?php

declare(strict_types=1);

namespace App\Strategus\Validators\Rules;

use Brick\Geo\Exception\GeometryException;
use Brick\Geo\Polygon;
use Rakit\Validation\Rule;
use Throwable;

class ValidWktPolygonRule extends Rule
{
    protected $message = "El campo :attribute debe ser un WKT de tipo POLYGON válido.";

    public function check(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            $geometry = Polygon::fromText($value);

            if (!$geometry instanceof Polygon || $geometry->isEmpty()) {
                return false;
            }

            $exteriorRing = $geometry->exteriorRing();
            if ($exteriorRing->numPoints() < 4) {
                return false;
            }

            $firstPoint = $exteriorRing->pointN(1);
            $lastPoint  = $exteriorRing->pointN($exteriorRing->numPoints());

            return $firstPoint->x() === $lastPoint->x()
                && $firstPoint->y() === $lastPoint->y();
        } catch (GeometryException | Throwable) {
            return false;
        }
    }
}
