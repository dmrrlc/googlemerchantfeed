# Google Merchant Feed Module for PrestaShop 9

Module gratuit pour générer un flux de produits compatible Google Merchant Center.

## Installation

1. **Télécharger le dossier `googlemerchantfeed`**

2. **Uploader via FTP/SFTP**
   - Connectez-vous à votre serveur Infomaniak
   - Naviguez vers `/modules/`
   - Uploadez le dossier complet `googlemerchantfeed`

3. **Installer le module**
   - Back-office PrestaShop → Modules → Module Manager
   - Cherchez "Google Merchant Feed"
   - Cliquez sur "Installer"

4. **Configurer le module**
   - Cliquez sur "Configurer" après l'installation
   - Choisissez la langue du flux (FR ou EN)
   - Définissez la devise (CHF)
   - Configurez les frais de livraison par défaut

## Configuration Google Merchant Center

1. Connectez-vous à [Google Merchant Center](https://merchants.google.com)

2. Allez dans **Produits → Flux**

3. Cliquez sur **Ajouter un flux principal**

4. Sélectionnez:
   - Pays: Suisse
   - Langue: Français (ou celle configurée)
   - Destination: Annonces Shopping et fiches produit gratuites

5. Choisissez **Récupération planifiée**

6. Entrez l'URL du flux fournie dans la configuration du module:
   ```
   https://airone.ch/modules/googlemerchantfeed/feed.php?key=VOTRE_CLE_SECRETE
   ```

7. Définissez la fréquence de récupération (quotidienne recommandée)

## Attributs générés

Le flux inclut automatiquement:

| Attribut | Source PrestaShop |
|----------|-------------------|
| id | ID produit + ID déclinaison |
| title | Nom du produit + attributs |
| description | Description courte ou longue |
| link | URL du produit |
| image_link | Image principale |
| additional_image_link | Images supplémentaires (max 10) |
| availability | Stock disponible |
| price | Prix TTC |
| brand | Fabricant (ION, DUOTONE, etc.) |
| gtin | Code EAN13 |
| mpn | Référence produit |
| condition | État (new/used/refurbished) |
| product_type | Chemin de catégorie |
| item_group_id | Regroupement des variantes |
| size | Taille (si attribut défini) |
| color | Couleur (si attribut défini) |
| shipping | Frais de livraison |

## Optimisations pour le kitesurf

Pour améliorer la qualité de vos fiches:

### EAN/GTIN codes
- Demandez les codes EAN à vos fournisseurs (Sideshore)
- Renseignez-les dans chaque fiche produit ou déclinaison
- Les codes GTIN améliorent le référencement Google Shopping

### Catégories Google
Mappez vos catégories vers la taxonomie Google:
- Harnais → `Sporting Goods > Outdoor Recreation > Water Sports > Kiteboarding`
- Ailes → `Sporting Goods > Outdoor Recreation > Water Sports > Kiteboarding > Kiteboarding Kites`
- Planches → `Sporting Goods > Outdoor Recreation > Water Sports > Kiteboarding > Kiteboards`

### Images
- Utilisez des images de haute qualité (min 800x800px)
- Fond blanc ou neutre de préférence
- Plusieurs angles par produit

## Dépannage

### Le flux ne s'affiche pas
- Vérifiez que la clé secrète est correcte dans l'URL
- Testez l'URL directement dans le navigateur
- Vérifiez les logs PHP dans Infomaniak

### Erreurs Google Merchant
- "Identifiant manquant": Ajoutez les codes EAN ou définissez `identifier_exists` à false (automatique)
- "Image non valide": Vérifiez que les images sont accessibles publiquement
- "Prix manquant": Vérifiez que les produits ont un prix défini

### Actualiser le flux
Le flux est généré dynamiquement à chaque requête. Google récupère les mises à jour selon la fréquence configurée.

## Sécurité

- L'URL contient une clé secrète générée automatiquement
- Vous pouvez régénérer cette clé dans la configuration si nécessaire
- Ne partagez pas l'URL publiquement

## Support

Module développé spécifiquement pour PrestaShop 9.x et testé avec la configuration airone.ch.

---

**Version**: 1.0.0  
**Compatibilité**: PrestaShop 9.0+  
**Licence**: MIT
