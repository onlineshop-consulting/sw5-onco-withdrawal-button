<?php

/**
 * MainSubscriber – Handles all frontend form events for the withdrawal plugin.
 *
 * 1. Sends a confirmation email copy to the customer when they submit a
 *    withdrawal form (clones the shop-owner notification).
 * 2. Assigns order data as JSON to the view so the frontend JS can
 *    prefill the withdrawal form fields.
 */

namespace OncoWithdrawal\Subscriber\Frontend;

use Doctrine\ORM\Exception\NotSupported;
use Enlight\Event\SubscriberInterface;
use Enlight_Components_Mail;
use Enlight_Components_Session_Namespace;
use Enlight_Event_EventArgs;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Attribute\Form as FormAttribute;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Form\Form;
use Shopware\Models\Order\Billing;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Shopware_Components_Modules;

class MainSubscriber implements SubscriberInterface
{
    private Shop $shop;
    private ModelManager $modelManager;
    private Enlight_Components_Session_Namespace $session;
    private Shopware_Components_Modules $modules;

    public function __construct(
        Shop                                 $shop,
        ModelManager                         $modelManager,
        Enlight_Components_Session_Namespace $session,
        Shopware_Components_Modules          $modules
    ) {
        $this->shop = $shop;
        $this->modelManager = $modelManager;
        $this->session = $session;
        $this->modules = $modules;
    }

    /** @inheritDoc */
    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware_Controllers_Frontend_Forms_commitForm_Mail' => 'onFormMailSent',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Forms' => 'onFormsPostDispatch',
        ];
    }

    // ==================================================================
    //  Mail copy
    // ==================================================================

    /**
     * Clone the shop-owner notification and send it to the customer.
     */
    public function onFormMailSent(Enlight_Event_EventArgs $args): void
    {
        $originalMail = $args->getReturn();
        if (!$originalMail instanceof Enlight_Components_Mail) {
            return;
        }

        /** @var \Shopware_Controllers_Frontend_Forms $subject */
        $subject = $args->get('subject');
        $request = $subject->Request();

        $email = $request->getPost('email');
        if (empty($email)) {
            return;
        }

        if (!$this->isWithdrawalForm($request->getParam('id') ?: $request->getParam('sFid'))) {
            return;
        }

        $copy = clone $originalMail;
        $copy->clearRecipients();
        $copy->addTo($email);
        $copy->send();
    }

    /**
     * Check whether the given form ID belongs to a withdrawal form.
     */
    private function isWithdrawalForm($formId): bool
    {
        if (!$formId) {
            return false;
        }

        $form = $this->modelManager->getRepository(Form::class)->find($formId);
        if (!$form instanceof Form) {
            return false;
        }

        $attribute = $form->getAttribute();

        return $attribute instanceof FormAttribute
            && $attribute->getOncoWithdrawalIsWithdrawalForm();
    }

    // ==================================================================
    //  Form prefilling – assign data to view, JS does the rest
    // ==================================================================

    /**
     * If conditions are met (user logged in, orderNumber param present),
     * assign order data as JSON to the view for client-side prefilling.
     */
    public function onFormsPostDispatch(Enlight_Event_EventArgs $args): void
    {
        /** @var \Shopware_Controllers_Frontend_Forms $subject */
        $subject = $args->get('subject');
        $request = $subject->Request();
        $view = $subject->View();

        // Only act on the initial form display, not on submit
        if ($request->getActionName() !== 'index') {
            return;
        }

        // Require a logged-in user
        $userId = $this->session->get('sUserId');
        if (!$userId || !$this->modules->Admin()->sCheckUser()) {
            return;
        }

        // Require an orderNumber query parameter
        $orderNumber = $request->getQuery('orderNumber');
        if (empty($orderNumber)) {
            return;
        }

        // Fetch data from the order
        $orderData = $this->getOrderData((string) $orderNumber, (string) $userId);
        if (empty($orderData)) {
            return;
        }

        // Assign to view – the form-elements template will output this as JSON
        $view->assign('oncoWithdrawalPrefill', $orderData);
    }

    // ------------------------------------------------------------------
    //  Data loading
    // ------------------------------------------------------------------

    /**
     * Load order + billing data for the given order number, scoped to the user.
     *
     * @return array<string, string> Field name → value map
     */
    private function getOrderData(string $orderNumber, string $userId): array
    {
        try {
            /** @var Order|null $order */
            $order = $this->modelManager->getRepository(Order::class)->findOneBy([
                'number' => $orderNumber,
                'customerId' => $userId,
            ]);

            if (!$order instanceof Order) {
                return [];
            }

            $data = [
                'order_number' => $order->getNumber() ?? '',
            ];

            // Add customer email
            $customer = $order->getCustomer();
            if ($customer instanceof Customer) {
                $data['email'] = $customer->getEmail();
            }

            // Add billing address fields
            $billing = $order->getBilling();
            if ($billing instanceof Billing) {
                $data += [
                    'firstname' => $billing->getFirstName(),
                    'lastname' => $billing->getLastName(),
                ];
            }

            return $data;
        } catch (NotSupported $e) {
            return [];
        }
    }
}
