<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\MacroNamespace;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedTestError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @help_topics/block_content.type.html.twig */
class __TwigTemplate_bf16322f4a31b7f898d34b5462571243 extends Template
{
    private Source $source;
    /**
     * @var array<string, MacroNamespace>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        $this->sandbox = $env->getExtension(SandboxExtension::class)->getChecker();
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 12
        $context["types_link_text"] = ('' === $tmp = \Twig\Extension\CoreExtension::captureOutput((function () use (&$context, $macros, $blocks) {
            yield t("Block types", array());
            return; yield;
        })())) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 13
        $context["types_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["types_link_text"] ?? null), "entity.block_content_type.collection"));
        // line 14
        yield "<h2>";
        yield t("Goal", array());
        yield "</h2>
<p>";
        // line 15
        yield t("Define a block type and its fields.", array());
        yield "</p>
<h2>";
        // line 16
        yield t("Steps", array());
        yield "</h2>
<ol>
  <li>";
        // line 18
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Structure</em> &gt; <em>@types_link</em>.", array("@types_link" => ($context["types_link"] ?? null), ));
        yield "</li>
  <li>";
        // line 19
        yield t("Click  <em>Add block type</em>.", array());
        yield "</li>
  <li>";
        // line 20
        yield t("Enter a label for this block type (shown in the administrative interface). Optionally, edit the automatically-generated machine name or the description.", array());
        yield "</li>
  <li>";
        // line 21
        yield t("Click <em>Save</em>. You will be returned to the <em>Block types</em> page.", array());
        yield "</li>
  <li>";
        // line 22
        yield t("Click <em>Manage fields</em> in the row of your new block type, and add the desired fields to your block type.", array());
        yield "</li>
  <li>";
        // line 23
        yield t("Optionally, click <em>Manage form display</em> or <em>Manage display</em> to change the editing form or field display for your block type.", array());
        yield "</li>
</ol>";
        return; yield;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/block_content.type.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDefaultEscapeStrategy(): string|false
    {
        return "html";
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  86 => 23,  82 => 22,  78 => 21,  74 => 20,  70 => 19,  66 => 18,  61 => 16,  57 => 15,  52 => 14,  50 => 13,  45 => 12,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/block_content.type.html.twig", "/var/www/html/core/modules/block_content/help_topics/block_content.type.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 12, "trans" => 12];
        static $filters = ["escape" => 18];
        static $functions = ["render_var" => 13, "help_route_link" => 13];
        static $tests = [];

        try {
            $this->sandbox->checkSecurity(
                [0 => "set", 1 => "trans"],
                [0 => "escape"],
                [0 => "render_var", 1 => "help_route_link"],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            } elseif ($e instanceof SecurityNotAllowedTestError && isset($tests[$e->getTestName()])) {
                $e->setTemplateLine($tests[$e->getTestName()]);
            }

            throw $e;
        }

    }
}
