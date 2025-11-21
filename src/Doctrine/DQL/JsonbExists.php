<?php

namespace App\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * JsonbExists ::= "JSONB_EXISTS" "(" ArithmeticPrimary "," StringPrimary ")"
 */
class JsonbExists extends FunctionNode
{
    public $jsonField = null;
    public $key = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonField = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->key = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'jsonb_exists(%s, %s)',
            $this->jsonField->dispatch($sqlWalker),
            $this->key->dispatch($sqlWalker)
        );
    }
}
