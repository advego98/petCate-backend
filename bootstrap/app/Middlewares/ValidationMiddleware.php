<?php

namespace BonVet\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ValidationMiddleware
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $data = $request->getParsedBody() ?? [];
        $errors = [];

        foreach ($this->rules as $field => $rules) {
            $value = $data[$field] ?? null;
            
            foreach (explode('|', $rules) as $rule) {
                $error = $this->validateRule($field, $value, $rule, $data);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        if (!empty($errors)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Datos de validación incorrectos',
                'errors' => $errors
            ]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    private function validateRule(string $field, $value, string $rule, array $data): ?string
    {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                if (is_null($value) || $value === '') {
                    return "El campo {$field} es obligatorio";
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "El campo {$field} debe ser un email válido";
                }
                break;

            case 'min':
                if ($value && strlen($value) < (int)$ruleParam) {
                    return "El campo {$field} debe tener al menos {$ruleParam} caracteres";
                }
                break;

            case 'max':
                if ($value && strlen($value) > (int)$ruleParam) {
                    return "El campo {$field} no debe superar {$ruleParam} caracteres";
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    return "El campo {$field} debe ser numérico";
                }
                break;

            case 'date':
                if ($value && !strtotime($value)) {
                    return "El campo {$field} debe ser una fecha válida";
                }
                break;

            case 'in':
                $allowedValues = explode(',', $ruleParam);
                if ($value && !in_array($value, $allowedValues)) {
                    return "El campo {$field} debe ser uno de: " . implode(', ', $allowedValues);
                }
                break;

            case 'unique':
                // Para validaciones únicas necesitaríamos acceso a la base de datos
                // Por simplicidad, lo omitimos aquí
                break;
        }

        return null;
    }
}