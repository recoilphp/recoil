<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev;

use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Instruments PHP code to allow the production of a per-strand stack trace.
 */
final class Instrumentor extends NodeVisitorAbstract
{
    public function __construct()
    {
        $factory = new ParserFactory();
        $this->parser = $factory->create(
            ParserFactory::ONLY_PHP7,
            new Lexer(['usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startTokenPos',
                'endTokenPos',
                'startFilePos',
                'endFilePos',
            ]])
        );

        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($this);
    }

    /**
     * Instrument the given source code and return the instrumented code.
     *
     * In order for a function to be identified as a Recoil coroutine it must
     * have a return type hint that resolves to the \Generator class, and a
     * "@recoil-coroutine" annotation in its documentation block.
     */
    public function instrument(string $source) : string
    {
        $this->position = 0;
        $this->input = $source;
        $this->output = '';

        $ast = $this->parser->parse($source);
        $this->traverser->traverse($ast);

        try {
            return $this->output . \substr($this->input, $this->position);
        } finally {
            $this->input = '';
            $this->output = '';
        }
    }

    /**
     * @access private
     */
    public function enterNode(Node $node)
    {
        if ($this->isCoroutine($node)) {
            $this->instrumentCoroutine($node);
        }
    }

    private function instrumentCoroutine(FunctionLike $node)
    {
        foreach ($node->getStmts() as $stmt) {
            if ($stmt instanceof Yield_) {
                if ($stmt->value === null) {
                    $start = $stmt->getAttribute('startFilePos');
                    $end   = $stmt->getAttribute('endFilePos');

                    $this->output .= \substr(
                        $this->input,
                        $this->position,
                        $end - $this->position + 1
                    );
                    $this->output .= ' new \Recoil\Dev\Trace\TraceYield(__FILE__, __LINE__)';
                    $this->position = $end + 1;
                } else {
                    $start = $stmt->value->getAttribute('startFilePos');
                    $end   = $stmt->value->getAttribute('endFilePos');

                    $this->output .= \substr(
                        $this->input,
                        $this->position,
                        $start - $this->position
                    );

                    $this->output .= 'new \Recoil\Dev\Trace\TraceYield(__FILE__, __LINE__, ';
                    $this->output .= \substr($this->input, $start, $end - $start + 1);
                    $this->output .= ')';
                    $this->position = $end + 1;
                }
            } elseif ($stmt instanceof YieldFrom) {
                // exit;
                $start = $stmt->expr->getAttribute('startFilePos');
                $end   = $stmt->expr->getAttribute('endFilePos');

                $this->output .= \substr(
                    $this->input,
                    $this->position,
                    $start - $this->position
                );

                $this->output .= 'new \Recoil\Dev\Trace\TraceYieldFrom(__FILE__, __LINE__, ';
                $this->output .= \substr($this->input, $start, $end - $start + 1);
                $this->output .= ')';
                $this->position = $end + 1;
            }
        }
    }

    private function isCoroutine(Node $node) : bool
    {
        if (!$node instanceof FunctionLike) {
            return false;
        }

        $returnType = $node->getReturnType();

        if (!$returnType instanceof FullyQualified) {
            return false;
        } elseif ($returnType->toString() !== 'Generator') {
            return false;
        }

        $doc = $node->getDocComment();

        if ($doc === null) {
            return false;
        } elseif (!preg_match('/@recoil-coroutine\b/m', $doc->getText())) {
            return false;
        }

        return true;
    }

    /**
     * @var Parser The PHP parser.
     */
    private $parser;

    /**
     * @var NodeTraverser The object that traverses the AST.
     */
    private $traverser;

    /**
     * @var string The original PHP source code.
     */
    private $input;

    /**
     * @var string The instrumented PHP code.
     */
    private $output;

    /**
     * @var int An index intot he original source code indicating the code that
     *          has already been processed.
     */
    private $position;
}
