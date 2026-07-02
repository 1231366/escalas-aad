<?php

namespace App\Services\Solver;

use RuntimeException;

/**
 * O serviço solver (Python/FastAPI) não respondeu ou devolveu um erro
 * inesperado. Nunca reimplementamos as regras no Laravel (ADR-0002) —
 * quando o solver está em baixo, a geração/validação simplesmente falha.
 */
class SolverUnavailableException extends RuntimeException {}
