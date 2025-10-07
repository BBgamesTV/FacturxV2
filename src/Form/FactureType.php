<?php

namespace App\Form;

use App\Entity\Facture;
use App\Entity\Client;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroFacture', TextType::class, [
                'label' => 'Numéro de facture',
                'attr' => [
                    'placeholder' => 'Ex: FAC-2025-001',
                    'class' => 'form-control'
                ]
            ])
            ->add('dateFacture', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de la facture',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateEcheance', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date d\'échéance',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('fournisseur', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner un fournisseur existant',
                'required' => false,
            ])
            ->add('acheteur', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner un acheteur existant',
                'required' => false,
            ])
            // Retire les MoneyType pour totalHT/totalTTC !
            ->add('lignes', CollectionType::class, [
                'entry_type' => FactureLigneType::class,
                'label' => 'Lignes de facture',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'attr' => [
                    'class' => 'facture-lignes-collection'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facture::class,
        ]);
    }
}
