<?php

/**
 * OncoWithdrawal – Bootstrap
 *
 * Main plugin class handling lifecycle events (install, update, activate,
 * deactivate, uninstall) and runtime event subscriptions.
 *
 * Subscribes to frontend PostDispatch to inject plugin config into Smarty templates.
 */

namespace OncoWithdrawal;

use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use OncoWithdrawal\Services\LifeCycleService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OncoWithdrawal extends Plugin
{
    /** Attribute column name on s_cms_support_attributes that flags withdrawal forms. */
    public const IS_WITHDRAWAL_FORM = 'onco_withdrawal_is_withdrawal_form';

    /** @var ContainerInterface */
    protected $container;

    /**
     * Caches that must be invalidated on any lifecycle change.
     *
     * @var string[]
     */
    private const CACHE_LIST = [
        InstallContext::CACHE_TAG_TEMPLATE,
        InstallContext::CACHE_TAG_CONFIG,
        InstallContext::CACHE_TAG_PROXY,
        InstallContext::CACHE_TAG_THEME,
        InstallContext::CACHE_TAG_HTTP,
    ];

    // ==================================================================
    //  Event subscriptions
    // ==================================================================

    /** @inheritDoc */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatch',
            'Enlight_Controller_Action_PostDispatch_Widgets' => 'onPostDispatch',
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatch',
        ];
    }

    /**
     * Assign the full plugin config to Smarty so templates can use
     * {$oncoWithdrawal.config.form}, {$oncoWithdrawal.config.showInFooter}, etc.
     */
    public function onPostDispatch(Enlight_Controller_ActionEventArgs $args): void
    {
        $view = $args->getSubject()->View();

        $view->assign('oncoWithdrawal', [
            'config' => $this->readConfig(),
        ]);
    }

    public function onPreDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->get('subject');
        $view = $controller->View();
        $view->addTemplateDir($this->getPath() . '/Resources/views');
    }

    // ==================================================================
    //  Lifecycle
    // ==================================================================

    /**
     * Create the withdrawal form attribute and default forms (DE + EN).
     */
    public function install(InstallContext $context): void
    {
        $this->getLifeCycleService()->install();

        parent::install($context);
    }

    /**
     * Clear caches so theme and config changes take effect immediately.
     */
    public function update(UpdateContext $context): void
    {
        $context->scheduleClearCache(self::CACHE_LIST);
    }

    public function activate(ActivateContext $context): void
    {
        $context->scheduleClearCache(self::CACHE_LIST);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $context->scheduleClearCache(self::CACHE_LIST);
    }

    /**
     * Remove forms and attribute unless the user chose to keep data.
     */
    public function uninstall(UninstallContext $context): void
    {
        $this->getLifeCycleService()->uninstall($context->keepUserData());

        $context->scheduleClearCache(self::CACHE_LIST);

        parent::uninstall($context);
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    /**
     * Read the plugin configuration for the given (or current) shop.
     *
     * @return array<string, mixed>
     */
    private function readConfig($shop = null): array
    {
        /** @var \Shopware\Components\Plugin\DBALConfigReader $reader */
        $reader = $this->container->get('shopware.plugin.config_reader');

        if (!$shop) {
            $shop = $this->container->initialized('shop')
                ? $this->container->get('shop')
                : null;
        }

        return $reader->getByPluginName($this->getName(), $shop);
    }

    /**
     * Build the LifeCycleService with the required dependencies.
     */
    private function getLifeCycleService(): LifeCycleService
    {
        return new LifeCycleService(
            $this->container->get('models'),
            $this->container->get('shopware_attribute.crud_service'),
            $this->container->get('config')
        );
    }
}
