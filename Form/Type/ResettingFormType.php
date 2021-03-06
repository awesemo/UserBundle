<?php

namespace Rz\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ResettingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('new', 'repeated', array(
            'type' => 'password',
            'options' => array('translation_domain' => 'RzUserBundle'),
            'first_options' => array('label' => 'form.label_new_password', 'attr'=>array('class'=>'span12')),
            'second_options' => array('label' => 'form.label_new_password_confirmation', 'attr'=>array('class'=>'span12')),
            'invalid_message' => 'fos_user.password.mismatch',
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Rz\UserBundle\Form\Model\ChangePassword',
            'intention'  => 'resetting',
        ));
    }

    public function getName()
    {
        return 'rz_user_resetting';
    }
}
