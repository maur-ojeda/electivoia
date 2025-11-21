<?php

namespace App\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * JsonArrayContains ::= "JSON_ARRAY_CONTAINS" "(" ArithmeticPrimary "," StringPrimary ")"
 * 
 * Verifica si un array JSON contiene un valor específico
 */
class JsonArrayContains extends FunctionNode
{
    public $jsonField = null;
    public $value = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonField = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        // Convertimos el JSON a texto y buscamos el valor usando el operador @>
        // Este operador verifica si un JSON contiene otro JSON
        // Ejemplo: '["ROLE_ADMIN", "ROLE_USER"]'::jsonb @> '"ROLE_ADMIN"'::jsonb
        return sprintf(
            '%s::jsonb @> (\'"\'|| %s ||\'"\')::jsonb',
            $this->jsonField->dispatch($sqlWalker),
            $this->value->dispatch($sqlWalker)
        );
    }
}
