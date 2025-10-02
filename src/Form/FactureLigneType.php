<?php

namespace App\Form;

use App\Entity\FactureLigne;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('designation', TextType::class, [
                'label' => 'Désignation',
                'attr' => ['placeholder' => 'Ex: Produit X']
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'attr' => ['placeholder' => 'SKU / Ref']
            ])
            ->add('quantite', NumberType::class, [
                'label' => 'Quantité',
                'attr' => ['step' => 0.01]
            ])
            ->add('unite', TextType::class, [
                'label' => 'Unité',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: pièce, kg']
            ])
            ->add('prixUnitaireHT', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Prix Unitaire HT',
                'attr' => ['step' => 0.01]
            ])
            ->add('tauxTVA', NumberType::class, [
                'label' => 'TVA (%)',
                'attr' => ['step' => 0.01]
            ])
            ->add('montantHT', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Montant HT'
            ])
            ->add('montantTVA', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Montant TVA'
            ])
            ->add('montantTTC', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Montant TTC'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FactureLigne::class,
        ]);
    }
}
