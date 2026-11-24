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
 * @final
 */
class Twig_Extension_Sandbox extends Twig_Extension
{
    protected $sandboxedGlobally;
    protected $sandboxed;
    protected $policy;

    public function __construct(Twig_Sandbox_SecurityPolicyInterface $policy, $sandboxed = false)
    {
        $this->policy = $policy;
        $this->sandboxedGlobally = $sandboxed;
    }

    public function getTokenParsers()
    {
        return array(new Twig_TokenParser_Sandbox());
    }

    public function getNodeVisitors()
    {
        return array(new Twig_NodeVisitor_Sandbox());
    }

    public function enableSandbox()
    {
        $this->sandboxed = true;
    }

    public function disableSandbox()
    {
        $this->sandboxed = false;
    }

    public function isSandboxed()
    {
        return $this->sandboxedGlobally || $this->sandboxed;
    }

    public function isSandboxedGlobally()
    {
        return $this->sandboxedGlobally;
    }

    public function setSecurityPolicy(Twig_Sandbox_SecurityPolicyInterface $policy)
    {
        $this->policy = $policy;
    }

    public function getSecurityPolicy()
    {
        return $this->policy;
    }

    public function checkSecurity($tags, $filters, $functions)
    {
        if ($this->isSandboxed()) {
            $this->policy->checkSecurity($tags, $filters, $functions);
        }
    }

    public function checkMethodAllowed($obj, $method)
    {
        if ($this->isSandboxed()) {
            $this->policy->checkMethodAllowed($obj, $method);
        }
    }

    public function checkPropertyAllowed($obj, $method)
    {
        if ($this->isSandboxed()) {
            $this->policy->checkPropertyAllowed($obj, $method);
        }
    }

    public function ensureToStringAllowed($obj)
    {
        if (!$this->isSandboxed() || (!is_object($obj) && !is_array($obj))) {
            return $obj;
        }

        $seen = null;

        return $this->ensureToStringAllowedRecursive($obj, $seen, 0);
    }

    /**
     * Checks the value, and the values it contains: an array or a Traversable is
     * cast element by element by filters like "join" or "replace" and by the "in" operator.
     */
    private function ensureToStringAllowedRecursive($obj, &$seen, $depth)
    {
        if ($depth > 64) {
            // deeper than any value a template can reasonably build: refuse instead of leaving it unchecked
            throw new Twig_Sandbox_SecurityError('The value is nested too deeply to be checked by the sandbox.');
        }

        if (is_array($obj)) {
            foreach ($obj as $key => $value) {
                if (is_object($value) || is_array($value)) {
                    $checked = $this->ensureToStringAllowedRecursive($value, $seen, $depth + 1);
                    if ($checked !== $value) {
                        $obj[$key] = $checked;
                    }
                }
            }

            return $obj;
        }

        if (!is_object($obj)) {
            return $obj;
        }

        if (method_exists($obj, '__toString')) {
            $this->policy->checkMethodAllowed($obj, '__toString');
        }

        if (!$obj instanceof Traversable || $obj instanceof Twig_Markup || $obj instanceof Twig_TemplateInterface) {
            return $obj;
        }

        if (null === $seen) {
            $seen = new SplObjectStorage();
        }
        if ($seen->contains($obj)) {
            return $obj;
        }
        $seen->attach($obj);

        if ($obj instanceof Generator) {
            // a generator can only be read once: read it here, and hand over what was checked
            $items = array();
            foreach ($obj as $value) {
                $items[] = $this->ensureToStringAllowedRecursive($value, $seen, $depth + 1);
            }

            return new ArrayIterator($items);
        }

        foreach ($obj as $value) {
            if (is_object($value) || is_array($value)) {
                $this->ensureToStringAllowedRecursive($value, $seen, $depth + 1);
            }
        }

        return $obj;
    }

    public function getName()
    {
        return 'sandbox';
    }
}

class_alias('Twig_Extension_Sandbox', 'Twig\Extension\SandboxExtension', false);
