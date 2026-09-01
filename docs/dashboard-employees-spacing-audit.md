# Audit des dashboards, suppressions et espacements

## Conclusion générale

Les dashboards Super Admin et Admin sont **principalement dynamiques côté backend** : leurs contrôleurs délèguent les données à `DashboardService`, et les templates utilisent les variables `dashboard.*`. Ils ne sont toutefois pas parfaitement homogènes : certaines métriques ou actions restent statiques dans les vues, et l’analyse avancée Admin parcourt tous les pointages historiques sans restriction de période.

## Dashboards

| Zone | État | Constat |
|---|---|---|
| Super Admin | Dynamique en grande partie | Les KPI proviennent de `DashboardService`, le CA utilise `prixMois`, les entreprises récentes sont chargées depuis la base et le graphique reçoit `chartValues`. Le compteur affiché est `totalPageViews`, fourni par `PageViewRepository`. |
| Admin | Dynamique en grande partie | Les KPI, services, pointages récents et graphiques utilisent `dashboard.*`. Le bloc `_advanced_analytics.html.twig` est alimenté par `advancedAnalytics`. |
| Admin — analytics avancées | Dynamique mais perfectible | Les rôles, services, postes, retards, absences, avances et salaires sont calculés depuis les entités. Le calcul des retards/absences parcourt toutefois tous les pointages historiques ; il faudrait idéalement filtrer par mois ou période pour éviter une charge inutile. |
| RH | Non concerné par la question principale | Le dashboard RH possède encore des éléments statiques dans les actions rapides selon la vue inspectée, notamment un texte indiquant `5 pointages en attente`. |

## Suppression des employés

### Admin

La suppression simple existe côté serveur avec la route POST `app_admin_employee_delete`. Elle vérifie le rôle Admin, l’appartenance à l’entreprise, le jeton CSRF et empêche l’Admin de supprimer son propre compte. Elle supprime également les dépendances Pointage, Planning, Congé, Démission, Paie, AvanceSalaire et Notification.

La suppression groupée existe avec la route POST `app_admin_employee_bulk_delete`. Elle vérifie le CSRF `bulk-delete`, convertit les identifiants reçus en entiers, recharge les utilisateurs depuis la base, limite la suppression aux utilisateurs de la même entreprise et ignore le compte connecté.

Cependant, la suppression simple est envoyée par JavaScript vers le formulaire bulk. Le script ajoute un champ `ids[]` au formulaire puis appelle `form.submit()`. Cela peut fonctionner, mais le listener est ajouté à chaque ouverture de la modal et peut provoquer des soumissions multiples après plusieurs ouvertures. La solution recommandée est d’utiliser un formulaire séparé pour la suppression simple ou de remplacer systématiquement le handler avec `onclick = ...`.

### RH

Dans l’archive auditée, `Rh/EmployeeController.php` ne contient ni route de suppression simple ni route de suppression groupée. Les boutons correspondants ne peuvent donc pas être considérés comme fonctionnels côté RH. La suppression existe actuellement côté Admin uniquement.

## Espacements et padding

Les pages n’utilisent pas exactement les mêmes espacements. Les dashboards emploient notamment `g-3`, `g-4`, `mt-4`, `p-3` et `p-4`, tandis que les listes employé ajoutent plusieurs variantes comme `py-1`, `py-2`, `py-3`, `py-4`, `py-5`, `px-2`, `px-3` et `px-4`.

La classe `top-50` utilisée pour centrer l’icône de recherche est une classe Bootstrap valide. L’audit initial l’avait confondue avec `p-50` ; il n’y avait pas de classe `p-50` réelle à corriger. Les différences d’espacement venaient surtout des conteneurs `px-4 py-3` et des paddings internes variables.

Les layouts utilisent également des conteneurs différents : certains affichent `container-fluid`, d’autres `container`. Les paddings internes des barres de navigation et sidebars varient aussi. Il n’existe donc pas actuellement de standard global garantissant le même padding sur chaque page.

## Verdict

Le dashboard Super Admin et le dashboard Admin sont dynamiques pour leurs données principales, mais ils ne sont pas totalement exempts de statique ou d’optimisations manquantes. La suppression simple et groupée est fonctionnelle côté Admin sous réserve de corriger le JavaScript de modal ; elle n’est pas implémentée côté RH dans cette archive. Enfin, les espacements ne sont pas uniformes et `p-50` doit être remplacé par une classe valide, par exemple `p-4`, ou par une classe CSS explicitement définie.

## Préparation de la correction

La feuille de styles globale ne définit pas `p-50`; cette classe est donc invalide dans Bootstrap 5. Les entités Pointage possèdent une propriété Doctrine `date`, ce qui permet de limiter les analytics à une fenêtre temporelle côté repository/service.

La correction retenue sera une fenêtre glissante configurable, par défaut les 90 derniers jours, avec limitation des résultats récurrents à 5 lignes. Les conteneurs des pages ciblées recevront une classe commune responsive, avec padding `px-3 px-md-4` et espacement vertical `py-3 py-md-4`, tandis que les cartes conserveront des espacements internes homogènes `p-3 p-md-4`.

Le repository Pointage utilise bien `p.date` et le repository AvanceSalaire utilise `a.dateDemande`. Les nouvelles méthodes de filtrage peuvent donc s’appuyer sur ces propriétés sans modifier le schéma.

Le partial `admin/_advanced_analytics.html.twig` utilise déjà des grilles Bootstrap (`row g-4`, `col-lg-4`) et des cartes responsives, mais le dashboard Admin ferme son conteneur principal avant l’inclusion du partial. La correction de spacing doit donc intégrer le partial dans le même conteneur.

Les layouts Admin, RH et Super Admin ont une structure commune avec une zone `.main-content` et un padding fixe ou semi-fixe. Une classe `.page-shell` responsive permet d’uniformiser le contenu des pages ciblées.
