<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Checks if casting an expression to __toString() is allowed by the sandbox.
 *
 * Wraps the expressions that the generated code (or the PHP callable behind a
 * filter, a function or a test) may cast to a string. With "check_security",
 * the allow-list of the template is checked before the expression is evaluated.
 *
 * Backport to 1.34 (CVE-2019-9942, CVE-2024-51754, CVE-2026-47732, CVE-2026-48806, CVE-2026-48807).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_CheckToString extends Twig_Node_Expression
{
    public function __construct(Twig_Node_Expression $expr, $checkSecurity = false)
    {
        parent::__construct(array('expr' => $expr), array('check_security' => (bool) $checkSecurity), $expr->getTemplateLine(), $expr->getNodeTag());
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler->raw('$this->env->getExtension(\'Twig_Extension_Sandbox\')->ensureToStringAllowed(');

        if ($this->getAttribute('check_security')) {
            // function arguments are evaluated in order: the allow-list is checked before the expression runs
            $compiler->raw('(($this->checkSecurity() || true) ? ')->subcompile($this->getNode('expr'))->raw(' : null)');
        } else {
            $compiler->subcompile($this->getNode('expr'));
        }

        $compiler->raw(')');
    }
}

class_alias('Twig_Node_CheckToString', 'Twig\Node\CheckToStringNode', false);
