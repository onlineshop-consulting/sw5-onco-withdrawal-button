<?php

/**
 * LifeCycleService – Handles install and uninstall logic for the plugin.
 *
 * Install:
 *   1. Adds a boolean attribute to s_cms_support_attributes to flag withdrawal forms.
 *   2. Creates pre-configured German and English withdrawal forms (if not already present).
 *
 * Uninstall:
 *   Removes the created forms and the attribute column (unless keepUserData is true).
 */

namespace OncoWithdrawal\Services;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OncoWithdrawal\OncoWithdrawal;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Form\Field;
use Shopware\Models\Form\Form;
use Shopware_Components_Config;

class LifeCycleService
{
    const DEFAULT_FORMS = [
        'de' => [
            'name' => 'Widerrufsformular',
            'text' => '<p>Innerhalb von 14 Tagen nach Vertragsschluss steht Ihnen ein gesetzliches Widerrufsrecht zu.'
                . 'Nutzen Sie dieses Formular, um Ihren Widerruf bequem online '
                . 'einzureichen. Füllen Sie dazu die untenstehenden Angaben aus und senden Sie das Formular '
                . 'mit einem Klick auf "Widerruf bestätigen" ab.<br><br>Eine Bestätigung mit Eingangsdatum '
                . 'und Uhrzeit wird Ihnen unmittelbar per E-Mail zugestellt.</p>',
            'text2' => '<p>Vielen Dank – Ihr Widerruf ist bei uns eingegangen und wird nun zeitnah bearbeitet.</p>',
            'emailTemplate' => "Vorname: {sVars.firstname}\nNachname: {sVars.lastname}\n"
                . "E-Mail-Adresse: {sVars.email}\n\n"
                . "Bestellnummer: {sVars.order_number}\n\n"
                . "Mitteilung: {sVars.message}\n\n"
                . "Zeitpunkt des Widerrufs: {sDateTime}",
            'emailSubject' => 'Widerruf des Vertrages - {sShopname}',
            'fields' => [
                ['name' => 'firstname',     'label' => 'Vorname',          'typ' => 'text',     'required' => 1, 'position' => 2,  'errorMsg' => 'Bitte geben Sie Ihren Vornamen ein.'],
                ['name' => 'lastname',      'label' => 'Nachname',         'typ' => 'text',     'required' => 1, 'position' => 3,  'errorMsg' => 'Bitte geben Sie Ihren Nachnamen ein.'],
                ['name' => 'email',         'label' => 'E-Mail-Adresse',   'typ' => 'email',    'required' => 1, 'position' => 11, 'errorMsg' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.'],
                ['name' => 'order_number',  'label' => 'Bestellnummer',    'typ' => 'text',     'required' => 1, 'position' => 7,  'errorMsg' => 'Bitte geben Sie die Bestellnummer ein.'],
                ['name' => 'message',       'label' => 'Mitteilung',       'typ' => 'textarea', 'required' => 0, 'position' => 10],
            ],
        ],
        'en' => [
            'name' => 'Withdrawal form',
            'text' => '<p>By law you may withdraw from your contract within 14 days of its conclusion.'
                . 'Use this form to submit your withdrawal conveniently online. Simply complete the '
                . 'fields below and press "Confirm withdrawal" to send your request.<br><br>A confirmation '
                . 'including the exact date and time of receipt will be sent to your email address straight away.</p>',
            'text2' => '<p>Thank you – your withdrawal has been registered and will be processed shortly.</p>',
            'emailTemplate' => "First Name: {sVars.firstname}\nLast Name: {sVars.lastname}\n"
                . "Email Address: {sVars.email}\n\n"
                . "Order Number: {sVars.order_number}\n\n"
                . "Message: {sVars.message}\n\n"
                . "Time of Withdrawal: {sDateTime}",
            'emailSubject' => 'Withdrawal from the contract - {sShopname}',
            'fields' => [
                ['name' => 'firstname',     'label' => 'First Name',       'typ' => 'text',     'required' => 1, 'position' => 2,  'errorMsg' => 'Please enter your first name.'],
                ['name' => 'lastname',      'label' => 'Last Name',        'typ' => 'text',     'required' => 1, 'position' => 3,  'errorMsg' => 'Please enter your last name.'],
                ['name' => 'email',         'label' => 'Email Address',    'typ' => 'email',    'required' => 1, 'position' => 11, 'errorMsg' => 'Please enter your email address.'],
                ['name' => 'order_number',  'label' => 'Order Number',     'typ' => 'text',     'required' => 1, 'position' => 7,  'errorMsg' => 'Please enter your order number.'],
                ['name' => 'message',       'label' => 'Message',          'typ' => 'textarea', 'required' => 0, 'position' => 10],
            ],
        ],
    ];

    private ModelManager $modelManager;
    private CrudService $attributeService;
    private Shopware_Components_Config $config;

    public function __construct(
        ModelManager $modelManager,
        CrudService $attributeService,
        Shopware_Components_Config $config
    ) {
        $this->modelManager = $modelManager;
        $this->attributeService = $attributeService;
        $this->config = $config;
    }

    // ==================================================================
    //  Install
    // ==================================================================

    /**
     * Run all installation steps: create attribute column, then default forms.
     *
     * @throws OptimisticLockException|ORMException
     */
    public function install(): void
    {
        $this->createAttribute();
        $this->createForms();
    }

    // ==================================================================
    //  Uninstall
    // ==================================================================

    /**
     * Remove forms and attribute column. Skipped entirely when the user
     * chose to preserve data during uninstall.
     *
     * @throws OptimisticLockException|ORMException
     */
    public function uninstall(bool $keepUserData): void
    {
        if ($keepUserData) {
            return;
        }

        $this->removeAttribute();
    }

    // ==================================================================
    //  Attribute management
    // ==================================================================

    /**
     * Register a boolean attribute on the form table so we can flag
     * withdrawal forms without relying on name matching.
     */
    private function createAttribute(): void
    {
        $this->attributeService->update(
            's_cms_support_attributes',
            OncoWithdrawal::IS_WITHDRAWAL_FORM,
            'boolean',
            [
                'label' => 'Ist ein Widerrufsformular',
                'helpText'         => 'Sendet Kopie und aendert Button-Text',
                'displayInBackend' => true,
                'custom' => false,
            ],
            null,
            true
        );

        $this->modelManager->generateAttributeModels(['s_cms_support_attributes']);
    }

    /**
     * Drop the attribute column and regenerate Doctrine models.
     */
    private function removeAttribute(): void
    {
        $this->attributeService->delete(
            's_cms_support_attributes',
            OncoWithdrawal::IS_WITHDRAWAL_FORM,
            true
        );

        $this->modelManager->generateAttributeModels(['s_cms_support_attributes']);
    }

    // ==================================================================
    //  Form management
    // ==================================================================

    /**
     * Create default withdrawal forms for each configured language,
     * skipping any language that already has a withdrawal form.
     *
     * @throws OptimisticLockException|ORMException
     */
    private function createForms(): void
    {
        $shopEmail = $this->config->get('mail') ?? 'EMAIL';

        foreach (self::DEFAULT_FORMS as $isoCode => $definition) {
            if ($this->withdrawalFormExists($isoCode)) {
                continue;
            }

            $form = new Form();
            $form->setName((string) $definition['name']);
            $form->setText((string) $definition['text']);
            $form->setText2((string) $definition['text2']);
            $form->setEmailTemplate((string) $definition['emailTemplate']);
            $form->setEmailSubject((string) $definition['emailSubject']);
            $form->setEmail($shopEmail);
            $form->setIsocode($isoCode);

            $this->modelManager->persist($form);
            $this->modelManager->flush();

            $attribute = $form->getAttribute();
            if (!$attribute) {
                $attribute = new \Shopware\Models\Attribute\Form();
                $attribute->setForm($form);
            }
            $attribute->setOncoWithdrawalIsWithdrawalForm(1);
            $this->modelManager->persist($attribute);

            foreach ($definition['fields'] as $fieldData) {
                $field = new Field();
                $field->setForm($form);
                $field->setName((string) $fieldData['name']);
                $field->setLabel((string) $fieldData['label']);
                $field->setTyp((string) $fieldData['typ']);
                $field->setRequired((int) $fieldData['required']);
                $field->setPosition((int) $fieldData['position']);
                $field->setErrorMsg(is_string($fieldData['errorMsg']) && $fieldData['errorMsg'] !== '' ? $fieldData['errorMsg'] : '');
                $field->setValue(is_string($fieldData['value']) && $fieldData['value'] !== '' ? $fieldData['value'] : '');
                $field->setClass(is_string($fieldData['class']) && $fieldData['class'] !== '' ? $fieldData['class'] : 'normal');
                $this->modelManager->persist($field);
            }

            $this->modelManager->flush();
        }
    }

    /**
     * Check whether a withdrawal form already exists for the given ISO code.
     */
    private function withdrawalFormExists(string $isoCode): bool
    {
        $count = $this->modelManager->createQueryBuilder()
            ->select('COUNT(form.id)')
            ->from(Form::class, 'form')
            ->innerJoin('form.attribute', 'attribute')
            ->where('attribute.oncoWithdrawalIsWithdrawalForm = 1')
            ->andWhere('form.isocode = :isocode')
            ->setParameter('isocode', $isoCode)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
