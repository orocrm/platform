<?php

namespace Oro\Bundle\AttachmentBundle\Controller;

use Oro\Bundle\AttachmentBundle\Entity\Attachment;
use Oro\Bundle\AttachmentBundle\Form\Handler\AttachmentHandler;
use Oro\Bundle\AttachmentBundle\Form\Type\AttachmentType;
use Oro\Bundle\AttachmentBundle\Manager\AttachmentManager;
use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Acl\Util\ClassUtils;

/**
 * The controller for Attachment entity.
 */
class AttachmentController extends AbstractController
{
    /**
     * @Route(
     *      "attachment/view/widget/{entityClass}/{entityId}",
     *      name="oro_attachment_widget_attachments"
     * )
     * @Acl(
     *      id="oro_attachment_view",
     *      type="entity",
     *      class="OroAttachmentBundle:Attachment",
     *      permission="VIEW"
     * )
     * @Template("@OroAttachment/Attachment/attachments.html.twig")
     */
    public function widgetAction($entityClass, $entityId)
    {
        $entityClass = $this->getEntityRoutingHelper()->resolveEntityClass($entityClass);
        return [
            'entityId'    => $entityId,
            'entityField' => ExtendHelper::buildAssociationName($entityClass),
        ];
    }

    /**
     * @Route("attachment/create/{entityClass}/{entityId}", name="oro_attachment_create")
     *
     * @Template("@OroAttachment/Attachment/update.html.twig")
     * @Acl(
     *      id="oro_attachment_create",
     *      type="entity",
     *      class="OroAttachmentBundle:Attachment",
     *      permission="CREATE"
     * )
     * @param Request $request
     * @param mixed $entityClass
     * @param mixed $entityId
     * @return array
     */
    public function createAction(Request $request, $entityClass, $entityId)
    {
        $entityRoutingHelper = $this->getEntityRoutingHelper();

        $entity      = $entityRoutingHelper->getEntity($entityClass, $entityId);
        $parentEntityClass = get_class($entity);

        $attachmentEntity = new Attachment();
        $attachmentEntity->setTarget($entity);

        $form       = $this->createForm(
            AttachmentType::class,
            $attachmentEntity,
            ['parentEntityClass' => $parentEntityClass, 'checkEmptyFile' => true]
        );

        $formAction = $entityRoutingHelper->generateUrlByRequest(
            'oro_attachment_create',
            $request,
            $entityRoutingHelper->getRouteParameters($entityClass, $entityId)
        );

        return $this->update($form, $formAction);
    }

    /**
     *
     * @Route("attachment/update/{id}", name="oro_attachment_update")
     *
     * @Template("@OroAttachment/Attachment/update.html.twig")
     * @Acl(
     *      id="oro_attachment_update",
     *      type="entity",
     *      class="OroAttachmentBundle:Attachment",
     *      permission="EDIT"
     * )
     * @param Request $request
     * @param Attachment $attachment
     * @return array
     */
    public function updateAction(Request $request, Attachment $attachment)
    {
        $formAction = $request->getUri();
        $form       = $this->createForm(
            AttachmentType::class,
            $attachment,
            [
                'parentEntityClass' => ClassUtils::getRealClass($attachment->getTarget()),
                'checkEmptyFile'    => false,
                'allowDelete'       => false
            ]
        );
        return $this->update($form, $formAction, true);
    }

    /**
     * @param FormInterface $form
     * @param string        $formAction
     * @param bool          $update
     *
     * @return array
     */
    protected function update(FormInterface $form, $formAction, $update = false)
    {
        $entity       = $form->getData();
        $responseData = [
            'entity' => $entity,
            'saved'  => false
        ];

        if ($update) {
            $responseData['update'] = true;
        }

        if ($this->get(AttachmentHandler::class)->process($form)) {
            $responseData['saved'] = true;
        } else {
            $responseData['form']       = $form->createView();
            $responseData['formAction'] = $formAction;
        }

        return $responseData;
    }

    /**
     * @return EntityRoutingHelper
     */
    protected function getEntityRoutingHelper(): EntityRoutingHelper
    {
        return $this->get(EntityRoutingHelper::class);
    }

    /**
     * @return AttachmentManager
     */
    protected function getAttachmentManager(): AttachmentManager
    {
        return $this->get(AttachmentManager::class);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                EntityRoutingHelper::class,
                AttachmentHandler::class,
                AttachmentManager::class,
            ]
        );
    }
}
