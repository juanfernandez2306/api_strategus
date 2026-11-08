<?php

declare(strict_types=1);

namespace App\Strategus\Validators;

use App\Shared\Validator\BaseValidator;
use App\Strategus\Validators\Rules\ValidWktPolygonRule;

class OilPalmGrowingAreaValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();

        $this->validator->addValidator('wkt_polygon', new ValidWktPolygonRule());
    }

    /**
     * @return array<string, mixed>
     */
    protected function customAttributes(): array
    {
        return [
            'uuid'              => 'identificador',
            'growing_area_code' => 'código de área de cultivo',
            'palm_count'        => 'conteo de palmas',
            'boundary_wkt'      => 'geometria tipo poligono',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'uuid'              => 'required|ramsey_uuid',
            'growing_area_code' => 'required|integer|min:1',
            'palm_count'        => 'required|integer|min:0',
            'boundary_wkt'      => 'required|wkt_polygon',
        ];
    }
}
