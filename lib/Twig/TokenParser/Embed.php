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
 * Embeds a template.
 *
 * @final
 */
class Twig_TokenParser_Embed extends Twig_TokenParser_Include
{
    public function parse(Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        $parent = $this->parser->getExpressionParser()->parseExpression();

        list($variables, $only, $ignoreMissing) = $this->parseArguments();

        // inject a fake parent to make the parent() function work
        // CVE-2026-133767: always a fake one. The real parent expression is handed to the parser, which sets it
        // before the node visitors run (it used to be set afterwards, so the sandbox never saw a dynamic parent)
        $stream->injectTokens(array(
            new Twig_Token(Twig_Token::BLOCK_START_TYPE, '', $token->getLine()),
            new Twig_Token(Twig_Token::NAME_TYPE, 'extends', $token->getLine()),
            new Twig_Token(Twig_Token::STRING_TYPE, '__parent__', $token->getLine()),
            new Twig_Token(Twig_Token::BLOCK_END_TYPE, '', $token->getLine()),
        ));

        $module = $this->parser->parseEmbeddedTemplate($stream, $parent, array($this, 'decideBlockEnd'));

        $this->parser->embedTemplate($module);

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_Node_Embed($module->getTemplateName(), $module->getAttribute('index'), $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }

    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endembed');
    }

    public function getTag()
    {
        return 'embed';
    }
}

class_alias('Twig_TokenParser_Embed', 'Twig\TokenParser\EmbedTokenParser', false);
