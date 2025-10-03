<?php

namespace App\Form;

use App\Entity\Facture;
use App\Entity\Client; // ou Fournisseur si tu as une entité séparée
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            ->add('fournisseur', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom', // ou 'raisonSociale' selon ton entité
                'label' => 'Fournisseur',
                'placeholder' => 'Sélectionner un fournisseur',
                'attr' => ['class' => 'form-control']
            ])
            ->add('acheteur', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom', // ou 'raisonSociale' selon ton entité
                'label' => 'Acheteur',
                'placeholder' => 'Sélectionner un client',
                'attr' => ['class' => 'form-control']
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
