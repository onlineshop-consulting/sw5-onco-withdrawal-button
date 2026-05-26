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

class MainSubscriber implements SubscriberInterface
{
    /** @var ModelManager */
    private $modelManager;

    /** @var Enlight_Components_Session_Namespace */
    private $session;

    public function __construct(
        ModelManager                         $modelManager,
        Enlight_Components_Session_Namespace $session
    ) {
        $this->modelManager = $modelManager;
        $this->session = $session;
    }

    /**
     * @inheritDoc
     *
     * @return array
     */
    public static function getSubscribedEvents()
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
     *
     * @return void
     */
    public function onFormMailSent(Enlight_Event_EventArgs $args)
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
     *
     * @return bool
     */
    private function isWithdrawalForm($formId)
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
     *
     * @return void
     */
    public function onFormsPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Forms $subject */
        $subject = $args->get('subject');
        $request = $subject->Request();
        $view = $subject->View();

        // Only act on the initial form display, not on submit
        if ($request->getActionName() !== 'index') {
            return;
        }
        //SW5.4
        $formData = $view->getAssign('sSupport');
        $attributes = isset($formData['attribute'])?$formData['attribute']:[];
        if(empty($attributes)) {
            /* @var $query \Doctrine\ORM\Query */
            $attributesRaw = Shopware()->Models()->getRepository(\Shopware\Models\Form\Form::class)
                ->getAttributesQuery($formData['id'])->getArrayResult();
            if(!empty($attributesRaw[0])) {
                $formData['attribute'] = $attributesRaw[0];
                $view->assign('sSupport', $formData);
            };
        }

        // Require a logged-in user
        $userId = $this->session->get('sUserId');
        if (!$userId || !Shopware()->Modules()->Admin()->sCheckUser()) {
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
     * @param string $orderNumber
     * @param string $userId
     *
     * @return array<string, string> Field name → value map
     */
    private function getOrderData($orderNumber, $userId)
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

            $orderNumberValue = $order->getNumber();
            $data = [
                'order_number' => $orderNumberValue !== null ? $orderNumberValue : '',
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
