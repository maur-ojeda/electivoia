<?php
// src/Doctrine/Functions/JsonbExistsAny.php

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * JsonbExistsAny ::= "JSONB_EXISTS_ANY" "(" StringPrimary "," StringPrimary ")"
 */
class JsonbExistsAny extends FunctionNode
{
    public Node $jsonbPathExpression;
    public Node $arrayExpression;

    public function getSql(SqlWalker $sqlWalker): string
    {
        // Genera la llamada a la función nativa de PostgreSQL
        return 'jsonb_exists_any(' .
            $sqlWalker->walkStringPrimary($this->jsonbPathExpression) . ', ' .
            $sqlWalker->walkStringPrimary($this->arrayExpression) .
            ')';
    }

    public function parse(Parser $parser): void
    {
        // Parsea el nombre de la función: JSONB_EXISTS_ANY
        // En Doctrine 3.x, esta constante debería existir.
        $parser->match(Lexer::T_IDENTIFIER);

        // Parsea el paréntesis de apertura
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        // Parsea el primer argumento (c.targetGrades)
        $this->jsonbPathExpression = $parser->StringPrimary();

        // Parsea la coma
        $parser->match(Lexer::T_COMMA);

        // Parsea el segundo argumento (:grades_to_check)
        $this->arrayExpression = $parser->StringPrimary();

        // Parsea el paréntesis de cierre
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
