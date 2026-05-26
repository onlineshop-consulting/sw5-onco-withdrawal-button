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
    const IS_WITHDRAWAL_FORM = 'onco_withdrawal_is_withdrawal_form';

    /** @var ContainerInterface */
    protected $container;

    /**
     * Caches that must be invalidated on any lifecycle change.
     *
     * @var string[]
     */
    const CACHE_LIST = [
        InstallContext::CACHE_TAG_TEMPLATE,
        InstallContext::CACHE_TAG_CONFIG,
        InstallContext::CACHE_TAG_PROXY,
        InstallContext::CACHE_TAG_THEME,
        InstallContext::CACHE_TAG_HTTP,
    ];

    // ==================================================================
    //  Event subscriptions
    // ==================================================================

    /**
     * @inheritDoc
     *
     * @return array
     */
    public static function getSubscribedEvents()
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
     *
     * @return void
     */
    public function onPostDispatch(Enlight_Controller_ActionEventArgs $args)
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
     *
     * @return void
     */
    public function install(InstallContext $context)
    {
        $this->getLifeCycleService()->install();

        parent::install($context);
    }

    /**
     * Clear caches so theme and config changes take effect immediately.
     *
     * @return void
     */
    public function update(UpdateContext $context)
    {
        $context->scheduleClearCache(self::CACHE_LIST);
    }

    /**
     * @return void
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(self::CACHE_LIST);
    }

    /**
     * @return void
     */
    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleClearCache(self::CACHE_LIST);
    }

    /**
     * Remove forms and attribute unless the user chose to keep data.
     *
     * @return void
     */
    public function uninstall(UninstallContext $context)
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
    private function readConfig($shop = null)
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
     *
     * @return LifeCycleService
     */
    private function getLifeCycleService()
    {
        return new LifeCycleService(
            $this->container->get('models'),
            $this->container->get('shopware_attribute.crud_service'),
            $this->container->get('config')
        );
    }
}
