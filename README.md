# FacturxV2

**Génération et gestion de factures électroniques conforme Factur-X/ZUGFeRD, basé sur Symfony.**  
Ce projet simplifie la création de factures PDF+XML, la sélection dynamique de clients, la gestion des modes de paiement et la conformité au standard Factur-X.

---

## Fonctionnalités

- Création, consultation et édition de factures clients
- Génération automatique de PDF factures avec pièce jointe XML Factur-X (profil BASIC / EN16931)
- Sélection ou création de clients dynamique dans les formulaires
- Calcul TVA multi-taux, remises, échéances, mode de paiement (codes UNTDID 4461)
- Interface utilisateur moderne (Twig)
- Validation structurée des exports XML pour conformité légale

---

## Stack technique

- **Backend** : PHP 8.1+, Symfony 6, Doctrine ORM
- **Frontend** : Twig, HTML5, JavaScript dynamique pour formulaires
- **PDF/XML** : Dompdf, [atgp/factur-x](https://github.com/atgp/factur-x)
- **Base de données** : MySQL/MariaDB

---

## Installation

1. **Cloner le projet**
    ```bash
    git clone
    cd facturxv2
    ```

2. **Installer les dépendances**
    ```bash
    composer install
    ```

3. **Configurer la base de données**
    - Modifier `.env` pour configurer la connexion DB
    - Créer la base de données :
    ```bash
    php bin/console doctrine:database:create
    ```
    - Exécuter les migrations :
    ```bash
    php bin/console doctrine:migrations:migrate
    ```
    - Charger les données de test :
    ```bash
    php bin/console doctrine:fixtures:load
    ```

4. **Lancer le serveur de développement**
    ```bash
    symfony server:start
    ```

5. **Accéder à l'application**
    - Ouvrir `http://localhost:8000` dans le navigateur

---

## Utilisation

- Gérer les clients et les produits
- Créer une facture :
- Sélectionner ou créer un client
- Renseigner pays ISO, devise, mode paiement via listes déroulantes standards
- Ajouter lignes de produits/services, TVA, remises, etc.
- Générer la facture PDF conforme
- Télécharger ou envoyer le PDF+XML officiellement conforme

---

## Codes standards utilisés

- **Pays** : ISO 3166-1 alpha2
- **Devise** : ISO 4217
- **Mode de paiement** : Codes UNTDID 4461 pour Factur-X

---

## Contribuer

- Fork du repository
- Créer une branche (`feature/ma-fonction`)
- Proposer un pull request

---

## Licence

Projet open-source sous licence MIT.

---

## Auteurs & liens

- Code par [BBgamesTV](https://github.com/BBgamesTV)
- Bibliothèque Factur-X par [atgp](https://github.com/atgp/factur-x)

---

> Pour toute question technique ou demande de support, crée une issue GitHub sur ce repository.