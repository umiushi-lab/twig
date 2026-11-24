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
 * Runs the sandbox allow-list check of the template against the current sandbox state.
 *
 * Added at the start of the display method, of each block and of each macro, so that
 * a template instance that was loaded or rendered outside of the sandbox is checked
 * again each time its code runs (CVE-2024-45411, CVE-2026-46638, CVE-2026-46636, CVE-2026-49981).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_CheckSecurityCall extends Twig_Node
{
    public function compile(Twig_Compiler $compiler)
    {
        $compiler->write("\$this->checkSecurity();\n");
    }
}

class_alias('Twig_Node_CheckSecurityCall', 'Twig\Node\CheckSecurityCallNode', false);
