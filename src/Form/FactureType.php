<?php

namespace App\Form;

use App\Entity\Facture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
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
            ->add('nomFournisseur', TextType::class, [
                'label' => 'Fournisseur',
                'attr' => ['placeholder' => 'Nom du fournisseur']
            ])
            ->add('nomAcheteur', TextType::class, [
                'label' => 'Acheteur',
                'attr' => ['placeholder' => 'Nom de l\'acheteur']
            ])
            ->add('totalHT', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Total HT',
                'attr' => ['step' => 0.01]
            ])
            ->add('totalTTC', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Total TTC',
                'attr' => ['step' => 0.01]
            ])
            // 🔥 Ajout des lignes de factures (CollectionType)
            ->add('lignes', CollectionType::class, [
                'entry_type' => FactureLigneType::class,
                'label' => 'Lignes de facture',
                'allow_add' => true,        // autorise l’ajout dynamique
                'allow_delete' => true,     // autorise la suppression
                'by_reference' => false,    // nécessaire pour les relations OneToMany
                'prototype' => true,        // utile pour JS dynamique
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
