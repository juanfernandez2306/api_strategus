<?php

namespace App\Strategus\Validators;

use Rakit\Validation\Validator;
use Rakit\Validation\Rule;
use DateTime;

class ExportExcelValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();

        // 1. REGLA: after_or_equal (Para validar el piso del 01-01-2026)
        $this->validator->addValidator('after_or_equal', new class extends Rule {
            protected $message = "La fecha :attribute no puede ser anterior a :after_or_equal.";

            // Permite a Rakit rellenar el parámetro dinámico de la regla en el mensaje
            public function fillParameters(array $params): Rule
            {
                $this->params['after_or_equal'] = $params[0] ?? '2026-01-01';
                return $this;
            }

            public function check(mixed $value): bool
            {
                $params = $this->getParameters();
                $minDateStr = $params[0] ?? null;

                if (!$value || !$minDateStr) return true;

                $fechaActual = DateTime::createFromFormat('Y-m-d', $value);
                $fechaMinima = DateTime::createFromFormat('Y-m-d', $minDateStr);

                if (!$fechaActual || !$fechaMinima) return false;

                return $fechaActual >= $fechaMinima;
            }
        });

        // 2. REGLA: before_or_equal (Para validar que no supere el día de 'hoy')
        $this->validator->addValidator('before_or_equal', new class extends Rule {
            protected $message = "La fecha :attribute no puede ser posterior a :before_or_equal.";

            public function fillParameters(array $params): Rule
            {
                $this->params['before_or_equal'] = $params[0] ?? '';
                return $this;
            }

            public function check(mixed $value): bool
            {
                $params = $this->getParameters();
                $maxDateStr = $params[0] ?? null;

                if (!$value || !$maxDateStr) return true;

                $fechaActual = DateTime::createFromFormat('Y-m-d', $value);
                $fechaMaxima = DateTime::createFromFormat('Y-m-d', $maxDateStr);

                if (!$fechaActual || !$fechaMaxima) return false;

                return $fechaActual <= $fechaMaxima;
            }
        });

        // 3. REGLA: max_date_range (Tu regla personalizada del delta de 60 días)
        $this->validator->addValidator('max_date_range', new class extends Rule {
            // Usamos el mismo nombre de la regla como marcador de posición (:max_date_range)
            protected $message = "El intervalo entre las fechas no puede ser mayor a :max_date_range días.";

            // Mapea el valor '60' al marcador de posición de Rakit de forma limpia
            public function fillParameters(array $params): Rule
            {
                $this->params['max_date_range'] = $params[0] ?? 62;
                return $this;
            }

            public function check(mixed $value): bool
            {
                $params = $this->getParameters(); 
                $maxDays = (int) ($params[0] ?? 62);
                
                $fechaInicioParam = $this->validation->getValue('fecha_inicio');

                if (!$fechaInicioParam || !$value) {
                    return true; 
                }

                $inicio = DateTime::createFromFormat('Y-m-d', $fechaInicioParam);
                $fin = DateTime::createFromFormat('Y-m-d', $value);

                if (!$inicio || !$fin) {
                    return false; 
                }

                $diferencia = $inicio->diff($fin);
                
                if ($diferencia->invert === 1) {
                    return false; 
                }

                return $diferencia->days <= $maxDays;
            }
        });
    }

    /**
     * Valida los rangos de fechas para la exportación a Excel y retorna los errores encontrados.
     */
    public function validate(array $data): array
    {
        $hoy = (new DateTime('now'))->format('Y-m-d');

        // Personalización de mensajes generales en español
        $this->validator->setMessages([
            'required'         => 'El campo :attribute es obligatorio.',
            'date'             => 'El formato de :attribute debe ser YYYY-MM-DD.',
            'before_or_equal'  => 'La fecha hasta no puede ser mayor a la fecha actual.',
            'after_or_equal'   => 'La fecha desde no puede ser anterior al 01-01-2026.'
        ]);

        // Mapeo estricto de las reglas
        $validation = $this->validator->make($data, [
            'fecha_inicio' => ['required', 'date:Y-m-d', 'after_or_equal:2026-01-01'],
            'fecha_fin'    => ['required', 'date:Y-m-d', "before_or_equal:{$hoy}", 'max_date_range:60']
        ]);

        $validation->validate();

        if ($validation->fails()) {
            return $validation->errors()->firstOfAll();
        }

        // Validación extra de coherencia cronológica por seguridad
        $inicio = DateTime::createFromFormat('Y-m-d', $data['fecha_inicio'] ?? '');
        $fin = DateTime::createFromFormat('Y-m-d', $data['fecha_fin'] ?? '');
        
        if ($inicio && $fin && $fin < $inicio) {
            return ['fecha_fin' => 'La fecha hasta no puede ser menor o anterior a la fecha desde.'];
        }

        return [];
    }
}