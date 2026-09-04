<?php

declare(strict_types=1);

namespace App\Strategus\Validators;

use App\Strategus\Validators\Rules\ValidWktPolygonRule;
use App\Users\Validators\BaseValidator;

class OilPalmGrowingAreaValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();

        $this->validator->addValidator('wkt_polygon', new ValidWktPolygonRule());
    }

    protected function customAttributes(): array
    {
        return [
            'uuid'              => 'identificador',
            'growing_area_code' => 'código de área de cultivo',
            'palm_count'        => 'conteo de palmas',
            'boundary_wkt'      => 'geometria tipo poligono',
        ];
    }

    protected function rules(): array
    {
        return [
            'uuid'              => 'required|regex:' . PositionRecordValidator::UUID_V7_REGEX,
            'growing_area_code' => 'required|integer|min:1',
            'palm_count'        => 'required|integer|min:0',
            'boundary_wkt'      => 'required|wkt_polygon',
        ];
    }
}
