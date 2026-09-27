<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recruiting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Recruiting-Ergänzungen (MVP-924/925).
return [
    'suitability' => [
        'title' => 'Matriz de idoneidad',
        'subtitle' => 'Competencias requeridas para «:title» y valoración de las candidaturas: una ayuda para la selección, no una decisión automática.',
        'requirements' => 'Competencias requeridas',
        'no_requirements' => 'Aún no se han definido requisitos.',
        'no_competencies' => 'Aún no hay competencias (catálogo de la plataforma de aprendizaje).',
        'competency' => 'Competencia',
        'level' => 'Nivel',
        'add' => 'Añadir requisito',
        'remove' => 'Eliminar',
        'confirm_remove' => '¿Eliminar esta competencia requerida?',
        'required' => 'Requerido :level',
        'matrix' => 'Candidaturas',
        'candidate' => 'Candidato',
        'gaps' => 'Carencias',
        'score' => 'Cumplimiento',
        'no_applications' => 'No hay candidaturas para este puesto.',
        'rating_title' => 'Valoración de competencias',
        'note' => 'Justificación (interna)',
        'save' => 'Guardar',
        'flash' => [
            'requirement' => 'Requisito guardado.',
            'requirement_removed' => 'Requisito eliminado.',
            'rated' => 'Valoración guardada.',
        ],
    ],
    'offer' => [
        'title' => 'Ofrecer horarios',
        'slot' => 'Horario :n',
        'mode' => 'Tipo de entrevista',
        'duration' => 'Duración (minutos)',
        'valid_days' => 'Válido (días)',
        'interviewer' => 'Entrevistador',
        'send' => 'Enviar invitación',
        'ics_title' => 'Entrevista de trabajo',
        'public_title' => 'Elegir horario de entrevista',
        'public_intro' => 'Elija un horario (:minutes minutos, :mode).',
        'choose' => 'Horarios ofrecidos',
        'confirm' => 'Confirmar este horario',
        'confirmed_title' => 'Horario confirmado',
        'confirmed_text' => 'Esperamos la entrevista el :when con :org. Le enviamos una confirmación con la cita.',
        'flash' => [
            'sent' => 'Invitación con :count horarios enviada.',
        ],
        'error' => [
            'no_email' => 'Esta candidatura no tiene una dirección de correo válida.',
            'no_slots' => 'Indique al menos un horario futuro.',
            'unavailable' => 'Este horario ya no está disponible.',
        ],
        'mail' => [
            'subject' => 'Su entrevista: elija un horario (:title)',
            'body' => "Estimado/a :name:\n\nnos gustaría conocerle. Elija uno de los siguientes horarios antes del :until:\n:slots\n\nElegir horario: :url",
            'confirmed_subject' => 'Confirmación de su entrevista',
            'confirmed_body' => "Estimado/a :name:\n\nsu entrevista tendrá lugar el :when (:mode). Encontrará la cita adjunta como evento de calendario.",
        ],
    ],
];
