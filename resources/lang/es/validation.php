<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'enum' => 'El valor seleccionado para :attribute es inválido.',
    'array' => 'El campo :attribute debe ser un arreglo.',
    'regex' => 'El formato de :attribute es inválido.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'process_number' => [
        'regex' => 'El número de radicado debe tener exactamente 23 dígitos numéricos.',
    ],
    'file' => [
        'required' => 'El archivo es requerido.',
        'file' => 'El campo debe ser un archivo válido.',
        'mimes' => 'El archivo debe ser de tipo: :values.',
    ],
    'organization_id' => [
        'required' => 'La organización es obligatoria.',
        'exists' => 'La organización seleccionada no es válida.',
    ],
    'organization' => [
        'name' => [
            'required' => 'El nombre de la organización es obligatorio.',
        ],
        'type' => [
            'required' => 'El tipo de organización es obligatorio.',
            'in' => 'El tipo debe ser persona natural o persona jurídica.',
        ],
        'phone' => [
            'required' => 'El teléfono es obligatorio.',
            'regex' => 'El teléfono debe tener 10 dígitos (celular). Se usará el indicativo +53 por defecto.',
        ],
        'email' => [
            'required' => 'El correo electrónico es obligatorio.',
            'email' => 'El correo electrónico no es válido.',
            'unique' => 'Ya existe una organización registrada con este correo electrónico.',
        ],
        'contact_person' => [
            'required_if' => 'La persona de contacto es obligatoria para persona jurídica.',
        ],
        'identification' => [
            'required' => 'La identificación es obligatoria.',
            'unique_cedula' => 'Ya existe una organización registrada con este número de identificación.',
            'unique_nit' => 'Ya existe una organización registrada con este NIT.',
        ],
    ],
    'custom' => [
        'alert_slug_only_for_alerta' => 'El filtro por tipo de alerta (alert_slug) solo aplica cuando type es actuacion_alerta.',
    ],
];
