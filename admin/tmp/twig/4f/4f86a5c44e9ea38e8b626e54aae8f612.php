<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* login/header.twig */
class __TwigTemplate_c649cbc5586c899627b29c81a74bfa91 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        if ((($context["session_expired"] ?? null) == true)) {
            // line 2
            yield "    <div id=\"modalOverlay\">
";
        }
        // line 4
        yield "<div class=\"container";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["add_class"] ?? null), "html", null, true);
        yield "\" style=\"margin-top: 40px;\"> ";
        // line 5
        yield "<div class=\"row\">
<div class=\"col-12 text-center\">
<a href=\"";
        // line 7
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Core::linkURL("https://github.com/yuntuanzi/"), "html", null, true);
        yield "\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"logo\">
<img src=\"../../static/images/default-avatar.png\" id=\"imLogo\" name=\"imLogo\" alt=\"喜灰论坛\" border=\"0\" 
     style=\"width: 80px; height: 80px; border-radius: 8px; max-width: 100%;\">
</a>
";
        // line 12
        yield "<h1 style=\"color: #165DFF; margin: 25px 0 15px; line-height: 1.2; font-size: 2.5rem;\">欢迎回来</h1>
";
        // line 14
        yield "<p style=\"margin: 0 0 25px; color: #222; font-size: 1.2rem;\">请填写你的数据库账密</p>

<noscript>
";
        // line 17
        yield $this->env->getFilter('error')->getCallable()(_gettext("Javascript must be enabled past this point!"));
        yield "
</noscript>

<div class=\"hide\" id=\"js-https-mismatch\">
";
        // line 21
        yield $this->env->getFilter('error')->getCallable()(_gettext("There is a mismatch between HTTPS indicated on the server and client. This can lead to a non working phpMyAdmin or a security risk. Please fix your server configuration to indicate HTTPS properly."));
        yield "
</div>";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "login/header.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  74 => 21,  67 => 17,  62 => 14,  59 => 12,  52 => 7,  48 => 5,  44 => 4,  40 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "login/header.twig", "/www/wwwroot/www.mua.cx/admin/templates/login/header.twig");
    }
}
