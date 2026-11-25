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
 * Twig_NodeVisitor_Sandbox implements sandboxing.
 *
 * @final
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_NodeVisitor_Sandbox extends Twig_BaseNodeVisitor
{
    protected $inAModule = false;
    protected $tags;
    protected $filters;
    protected $functions;

    protected function doEnterNode(Twig_Node $node, Twig_Environment $env)
    {
        if ($node instanceof Twig_Node_Module) {
            $this->inAModule = true;
            $this->tags = array();
            $this->filters = array();
            $this->functions = array();

            // {% extends expr %}: the loader casts the parent name to a string, and the expression
            // may run before the display method (block or macro reached on a loaded instance)
            if ($node->hasNode('parent') && $this->isWrappable($node->getNode('parent'))) {
                $node->setNode('parent', new Twig_Node_CheckToString($node->getNode('parent'), true));
            }

            return $node;
        } elseif ($this->inAModule) {
            // look for tags
            if ($node->getNodeTag() && !isset($this->tags[$node->getNodeTag()])) {
                $this->tags[$node->getNodeTag()] = $node;
            }

            // look for filters
            if ($node instanceof Twig_Node_Expression_Filter && !isset($this->filters[$node->getNode('filter')->getAttribute('value')])) {
                $this->filters[$node->getNode('filter')->getAttribute('value')] = $node;
            }

            // look for functions
            if ($node instanceof Twig_Node_Expression_Function && !isset($this->functions[$node->getAttribute('name')])) {
                $this->functions[$node->getAttribute('name')] = $node;
            }

            // the .. operator is equivalent to the range() function
            if ($node instanceof Twig_Node_Expression_Binary_Range && !isset($this->functions['range'])) {
                $this->functions['range'] = $node;
            }

            // blocks and macros can be reached without going through the display method of their template
            if ($node instanceof Twig_Node_Block || $node instanceof Twig_Node_Macro) {
                $node->setNode('body', new Twig_Node(array(new Twig_Node_CheckSecurityCall(), $node->getNode('body'))));
            }

            // wrap the expressions that are cast to a string at runtime
            $this->wrapStringCoercedChildren($node);

            // wrap print to check __toString() calls
            if ($node instanceof Twig_Node_Print) {
                return new Twig_Node_SandboxedPrint($node->getNode('expr'), $node->getTemplateLine(), $node->getNodeTag());
            }
        }

        return $node;
    }

    protected function doLeaveNode(Twig_Node $node, Twig_Environment $env)
    {
        if ($node instanceof Twig_Node_Module) {
            $this->inAModule = false;

            $node->setNode('display_start', new Twig_Node(array(new Twig_Node_CheckSecurityCall(), $node->getNode('display_start'))));
            $node->setNode('class_end', new Twig_Node(array(new Twig_Node_CheckSecurity($this->filters, $this->tags, $this->functions), $node->getNode('class_end'))));
        }

        return $node;
    }

    // tests of the core extension that never cast their operands to a string
    private static $safeTests = array('defined' => 1, 'null' => 1, 'none' => 1, 'same as' => 1, 'sameas' => 1, 'iterable' => 1, 'empty' => 1, 'even' => 1, 'odd' => 1, 'divisible by' => 1, 'divisibleby' => 1, 'constant' => 1);

    private function wrapStringCoercedChildren(Twig_Node $node)
    {
        if ($node instanceof Twig_Node_Expression_Filter) {
            $this->wrapNode($node, 'node');
            $this->wrapChildren($node->getNode('arguments'));
        } elseif ($node instanceof Twig_Node_Expression_Function) {
            $this->wrapChildren($node->getNode('arguments'));
        } elseif ($node instanceof Twig_Node_Expression_Test) {
            if (!isset(self::$safeTests[$node->getAttribute('name')])) {
                $this->wrapNode($node, 'node');
                if ($node->hasNode('arguments') && null !== $node->getNode('arguments')) {
                    $this->wrapChildren($node->getNode('arguments'));
                }
            } elseif ('constant' === $node->getAttribute('name') && $node->hasNode('arguments') && null !== $node->getNode('arguments')) {
                // CVE-2026-133764: the constant test never casts its input, but it concatenates its arguments
                // into a constant name (constant(get_class($object).'::'.$name))
                $this->wrapChildren($node->getNode('arguments'));
            }
        } elseif ($node instanceof Twig_Node_Expression_Binary_Concat
            || $node instanceof Twig_Node_Expression_Binary_Equal || $node instanceof Twig_Node_Expression_Binary_NotEqual
            || $node instanceof Twig_Node_Expression_Binary_Greater || $node instanceof Twig_Node_Expression_Binary_GreaterEqual
            || $node instanceof Twig_Node_Expression_Binary_Less || $node instanceof Twig_Node_Expression_Binary_LessEqual
            || $node instanceof Twig_Node_Expression_Binary_In || $node instanceof Twig_Node_Expression_Binary_NotIn
            || $node instanceof Twig_Node_Expression_Binary_Matches || $node instanceof Twig_Node_Expression_Binary_Range
            || $node instanceof Twig_Node_Expression_Binary_StartsWith || $node instanceof Twig_Node_Expression_Binary_EndsWith
        ) {
            $this->wrapNode($node, 'left');
            $this->wrapNode($node, 'right');
        } elseif ($node instanceof Twig_Node_Expression_GetAttr) {
            // a dynamic attribute name is cast to a string, and the PHP method may cast any of its arguments
            $this->wrapNode($node, 'attribute');
            if ($node->hasNode('arguments') && $node->getNode('arguments') instanceof Twig_Node_Expression_Array) {
                $this->wrapChildren($node->getNode('arguments'), 1);
            }
        } elseif ($node instanceof Twig_Node_Expression_Array) {
            // dynamic mapping keys are cast to a string
            $this->wrapChildren($node, 0);
        } elseif ($node instanceof Twig_Node_Expression_BlockReference) {
            $this->wrapNode($node, 'name');
        } elseif ($node instanceof Twig_Node_Include || $node instanceof Twig_Node_Import) {
            // the loader casts the template name to a string
            $this->wrapNode($node, 'expr');
        }
    }

    /**
     * @param int|null $parity null for all the children, 0 for the keys of an array node, 1 for its values
     */
    private function wrapChildren(Twig_Node $node, $parity = null)
    {
        $i = 0;
        foreach ($node as $name => $child) {
            if (null === $parity || $parity === $i % 2) {
                $this->wrapNode($node, $name);
            }
            ++$i;
        }
    }

    private function wrapNode(Twig_Node $node, $name)
    {
        if (!$node->hasNode($name)) {
            return;
        }

        $expr = $node->getNode($name);
        if ($this->isWrappable($expr)) {
            $node->setNode($name, new Twig_Node_CheckToString($expr));
        }
    }

    private function isWrappable($expr)
    {
        if (!$expr instanceof Twig_Node_Expression || $expr instanceof Twig_Node_CheckToString || $expr instanceof Twig_Node_Expression_Constant) {
            return false;
        }

        // nodes that are assigned to, or that compile to something else than a value
        if ($expr instanceof Twig_Node_Expression_AssignName || $expr instanceof Twig_Node_Expression_TempName) {
            return false;
        }

        // _self is internal
        if ($expr instanceof Twig_Node_Expression_Name && '_self' === $expr->getAttribute('name')) {
            return false;
        }

        return true;
    }

    public function getPriority()
    {
        return 0;
    }
}

class_alias('Twig_NodeVisitor_Sandbox', 'Twig\NodeVisitor\SandboxNodeVisitor', false);
