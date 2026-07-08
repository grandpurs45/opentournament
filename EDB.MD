Expression de besoin — OpenTournament
1. Présentation du projet
OpenTournament est une application web open source permettant d’organiser, suivre et afficher des tournois de manière générique.
L’application doit pouvoir gérer plusieurs types de compétitions grâce à un système de plugins :
•	Mölkky ;
•	pétanque ;
•	fléchettes ;
•	baby-foot ;
•	belote ;
•	échecs ;
•	jeux de société ;
•	tout autre jeu basé sur des matchs, scores, classements ou éliminations.
L’objectif initial est de disposer d’une application fonctionnelle pour organiser un tournoi de Mölkky, tout en construisant dès le départ un moteur générique réutilisable.
2. Objectifs principaux
OpenTournament doit permettre :
•	de créer un tournoi ;
•	d’ajouter des équipes ou joueurs ;
•	de choisir un type de tournoi via plugin ;
•	de générer des poules ;
•	de générer les matchs ;
•	de saisir les scores ;
•	de calculer automatiquement les classements ;
•	de générer les phases finales ;
•	d’afficher le suivi sur un écran public ;
•	de proposer une vue mobile via QR Code ;
•	de fonctionner localement sans Internet ;
•	d’être déployable facilement avec Docker.
3. Philosophie du projet
Le cœur de l’application ne doit pas être spécifique au Mölkky.
Il doit gérer les notions génériques suivantes :
•	tournoi ;
•	participant ;
•	équipe ;
•	match ;
•	manche ;
•	score ;
•	poule ;
•	classement ;
•	phase finale ;
•	terrain/table ;
•	planning ;
•	affichage public.
Les règles spécifiques à chaque jeu doivent être portées par des plugins.
Exemple :
•	le plugin Mölkky définit la victoire à 50 points ;
•	le plugin fléchettes peut définir des manches en 301 ou 501 ;
•	le plugin pétanque peut définir une victoire à 13 points ;
•	le plugin belote peut définir un score cible et des manches ;
•	le plugin baby-foot peut définir un nombre de buts gagnants.
4. Nom du projet
Nom retenu : OpenTournament
Baseline possible :
OpenTournament — The Open Source Tournament Management Platform
ou en français :
OpenTournament — La plateforme open source de gestion de tournois
5. Utilisateurs
Administrateur
L’administrateur organise le tournoi.
Il peut :
•	créer un tournoi ;
•	choisir un plugin de jeu ;
•	configurer les règles ;
•	ajouter les équipes ou joueurs ;
•	générer les poules ;
•	générer les matchs ;
•	saisir les scores ;
•	corriger un résultat ;
•	générer les phases finales ;
•	afficher le QR Code ;
•	clôturer le tournoi.
Joueur
Le joueur accède à une vue mobile publique via QR Code.
Il peut consulter :
•	son équipe ;
•	son prochain match ;
•	son terrain ;
•	ses résultats ;
•	le classement de sa poule ;
•	le tableau final ;
•	le podium.
Public
Le public consulte une vue plein écran affichée sur TV ou vidéoprojecteur.
La vue publique affiche :
•	le nom du tournoi ;
•	les matchs en cours ;
•	les prochains matchs ;
•	les classements ;
•	le tableau final ;
•	le podium ;
•	le QR Code d’accès mobile.
6. Fonctionnement général
Un tournoi est créé à partir :
•	d’un nom ;
•	d’une date ;
•	d’un type de jeu ;
•	d’un format de compétition ;
•	d’un nombre de terrains ;
•	d’une liste de participants ;
•	d’un plugin de règles.
Exemple :
•	Nom : Tournoi Mölkky Sainte-Barbe
•	Plugin : Mölkky
•	Format : Poules + phases finales
•	Terrains : 2
•	Score cible : 50
•	Points victoire : 3
•	Points défaite : 0
7. Architecture fonctionnelle
L’application doit être divisée en deux grandes parties :
7.1 Core OpenTournament
Le cœur applicatif doit gérer :
•	création des tournois ;
•	participants ;
•	équipes ;
•	poules ;
•	matchs ;
•	terrains ;
•	phases finales ;
•	planning ;
•	affichage TV ;
•	QR Code ;
•	exports ;
•	persistance des données ;
•	interface d’administration.
7.2 Plugins de jeu
Chaque plugin doit pouvoir définir :
•	le nom du jeu ;
•	le type de score ;
•	les règles de victoire ;
•	les règles de validation d’un score ;
•	le calcul du vainqueur ;
•	les règles de classement ;
•	les éventuels champs spécifiques de saisie ;
•	les libellés à afficher dans l’interface.
8. Plugins attendus
8.1 Plugin Mölkky
Règles par défaut :
•	score cible : 50 ;
•	victoire à 50 points ;
•	un match oppose deux équipes ;
•	score final saisi manuellement ;
•	vainqueur déterminé automatiquement ;
•	score gagnant maximum : 50 ;
•	classement par victoires et différentiel.
8.2 Plugin Pétanque
Règles par défaut :
•	score cible : 13 ;
•	match en une manche ;
•	victoire à 13 points ;
•	classement par victoires, points marqués, points encaissés.
8.3 Plugin Fléchettes
Règles possibles :
•	mode 301 ;
•	mode 501 ;
•	nombre de manches configurable ;
•	vainqueur au meilleur des manches.
8.4 Plugin Baby-foot
Règles par défaut :
•	score cible configurable ;
•	exemple : premier à 10 buts ;
•	match simple ;
•	classement par victoires et différence de buts.
8.5 Plugin générique
Un plugin générique doit permettre de créer un tournoi simple avec :
•	score numérique ;
•	score cible configurable ;
•	victoire au plus haut score ou score cible ;
•	classement standard.
Ce plugin servira de fallback.
9. Formats de tournoi
L’application doit supporter plusieurs formats.
9.1 Poules uniquement
Les participants sont répartis en poules.
Chaque équipe joue contre toutes les autres équipes de sa poule.
9.2 Poules + phases finales
Après les poules, les meilleurs participants sont qualifiés pour un tableau final.
Formats possibles :
•	finale directe ;
•	demi-finales ;
•	quarts de finale ;
•	petite finale optionnelle.
9.3 Élimination directe
Tous les participants entrent directement dans un tableau.
9.4 Championnat
Chaque participant rencontre tous les autres.
Ce format peut être prévu dans la roadmap.
10. Génération des poules
Le système doit générer automatiquement les poules.
Contraintes :
•	répartition équilibrée ;
•	poules de taille proche ;
•	minimum 3 participants par poule si possible ;
•	possibilité de modifier manuellement les poules.
Exemples :
•	8 équipes → 2 poules de 4 ;
•	12 équipes → 3 poules de 4 ;
•	16 équipes → 4 poules de 4 ;
•	20 équipes → 4 poules de 5.
11. Génération des matchs
Pour chaque poule, l’application doit générer les matchs en round-robin.
Chaque participant doit rencontrer tous les autres participants de sa poule une fois.
Un match contient :
•	numéro de match ;
•	phase ;
•	poule ;
•	terrain ;
•	participant A ;
•	participant B ;
•	score A ;
•	score B ;
•	vainqueur ;
•	statut ;
•	ordre de passage.
Statuts possibles :
•	à venir ;
•	en cours ;
•	terminé ;
•	annulé.
12. Saisie des scores
La saisie des scores doit être rapide et simple.
L’administrateur doit pouvoir :
•	ouvrir la liste des matchs ;
•	filtrer par poule, terrain ou statut ;
•	saisir un score ;
•	valider le résultat ;
•	corriger un score ;
•	relancer le calcul du classement.
L’application doit empêcher les incohérences évidentes selon les règles du plugin actif.
Exemple pour Mölkky :
•	50 - 37 : valide ;
•	42 - 50 : valide ;
•	51 - 30 : invalide ;
•	50 - 50 : invalide.
13. Classements
Les classements doivent être calculés automatiquement à partir des matchs terminés.
Colonnes génériques :
•	position ;
•	participant ;
•	matchs joués ;
•	victoires ;
•	défaites ;
•	points classement ;
•	points marqués ;
•	points encaissés ;
•	différence ;
•	statut de qualification.
Critères de départage par défaut :
1.	points classement ;
2.	nombre de victoires ;
3.	différence de points ;
4.	points marqués ;
5.	confrontation directe ;
6.	décision manuelle.
Chaque plugin doit pouvoir adapter ces critères.
14. Phases finales
L’application doit générer automatiquement les phases finales à partir des classements.
Exemple avec 4 qualifiés :
•	Demi-finale 1 : 1er Poule A vs 2e Poule B ;
•	Demi-finale 2 : 1er Poule B vs 2e Poule A ;
•	Finale : vainqueurs des demi-finales ;
•	Petite finale : perdants des demi-finales.
L’administrateur doit pouvoir :
•	choisir le nombre de qualifiés ;
•	générer le tableau ;
•	modifier manuellement les qualifiés ;
•	saisir les scores ;
•	suivre la progression du tableau.
15. Interface Administration
Pages attendues :
Accueil
•	liste des tournois ;
•	bouton créer un tournoi ;
•	reprise d’un tournoi existant.
Création tournoi
•	nom ;
•	date ;
•	plugin de jeu ;
•	format ;
•	nombre de terrains ;
•	règles principales.
Participants
•	ajout ;
•	modification ;
•	suppression ;
•	import simple ;
•	couleurs ou emojis facultatifs.
Poules
•	génération automatique ;
•	modification manuelle ;
•	visualisation par poule.
Matchs
•	liste complète ;
•	filtre par terrain ;
•	filtre par poule ;
•	filtre par statut ;
•	saisie rapide des scores.
Classements
•	classement par poule ;
•	classement général ;
•	qualifiés ;
•	export.
Phases finales
•	génération du tableau ;
•	modification ;
•	saisie des scores ;
•	podium.
Affichage
•	lien vers la vue TV ;
•	QR Code ;
•	lien vers la vue joueur.
16. Interface TV
URL proposée :
/display/:tournamentId
La vue TV doit être lisible de loin.
Elle doit afficher :
•	titre du tournoi ;
•	logo ou icône ;
•	QR Code ;
•	matchs en cours ;
•	prochains matchs ;
•	classements ;
•	tableau final ;
•	podium.
La page doit se mettre à jour automatiquement.
Pour une première version, un polling toutes les 5 secondes est acceptable.
17. Interface mobile joueur
URL proposée :
/t/:tournamentId
Fonctionnalités :
•	choix de son équipe ;
•	sauvegarde locale de l’équipe choisie ;
•	prochain match ;
•	terrain ;
•	résultats de son équipe ;
•	classement ;
•	tableau final ;
•	podium.
La vue doit être simple, responsive et utilisable sur smartphone.
18. QR Code
L’application doit générer un QR Code vers la vue mobile.
Le QR Code doit être visible :
•	sur l’écran TV ;
•	dans l’interface admin ;
•	sur une page imprimable.
19. Exports
Exports attendus :
•	CSV des participants ;
•	CSV des matchs ;
•	CSV des résultats ;
•	CSV des classements.
Exports optionnels :
•	PDF du classement ;
•	PDF du tableau final ;
•	feuille imprimable des matchs ;
•	diplôme des vainqueurs.
20. Statistiques bonus
L’application peut afficher :
•	nombre total de matchs ;
•	nombre de matchs terminés ;
•	meilleure attaque ;
•	meilleure défense ;
•	plus gros différentiel ;
•	match le plus serré ;
•	équipe invaincue ;
•	podium final.
21. Contraintes techniques
L’application doit être :
•	web ;
•	responsive ;
•	dockerisable ;
•	utilisable en local ;
•	utilisable sans Internet ;
•	simple à déployer ;
•	compatible PC, tablette, smartphone ;
•	compatible affichage TV.
22. Stack technique proposée
Stack possible :
•	Frontend : React ;
•	Backend : Node.js avec Express ou Fastify ;
•	Base de données : SQLite ;
•	ORM : Prisma ;
•	QR Code : librairie JavaScript ;
•	Temps réel : polling au départ, WebSocket ensuite ;
•	Déploiement : Docker + Docker Compose.
La stack peut être adaptée si elle reste simple et maintenable.
23. Docker
Le projet doit fournir :
•	Dockerfile ;
•	docker-compose.yml ;
•	volume persistant pour SQLite ;
•	fichier .env.example ;
•	README clair.
Commande attendue :
docker compose up -d
URL attendue :
http://localhost:8080
24. Modèle de données minimal
Tournament
•	id ;
•	name ;
•	date ;
•	pluginKey ;
•	format ;
•	numberOfFields ;
•	status ;
•	settings ;
•	createdAt ;
•	updatedAt.
Participant
•	id ;
•	tournamentId ;
•	name ;
•	type ;
•	players ;
•	color ;
•	emoji ;
•	createdAt.
Pool
•	id ;
•	tournamentId ;
•	name ;
•	order.
PoolParticipant
•	id ;
•	poolId ;
•	participantId.
Match
•	id ;
•	tournamentId ;
•	poolId ;
•	phase ;
•	round ;
•	fieldNumber ;
•	participantAId ;
•	participantBId ;
•	scoreA ;
•	scoreB ;
•	winnerParticipantId ;
•	status ;
•	scheduledOrder ;
•	metadata ;
•	createdAt ;
•	updatedAt.
PluginConfig
•	id ;
•	key ;
•	name ;
•	version ;
•	settingsSchema.
25. Structure de projet souhaitée
Exemple :
opentournament/
├── backend/
│   ├── src/
│   │   ├── core/
│   │   ├── plugins/
│   │   │   ├── molkky/
│   │   │   ├── petanque/
│   │   │   ├── darts/
│   │   │   └── generic/
│   │   ├── routes/
│   │   ├── services/
│   │   └── database/
│   └── package.json
├── frontend/
│   ├── src/
│   │   ├── pages/
│   │   ├── components/
│   │   ├── admin/
│   │   ├── display/
│   │   └── mobile/
│   └── package.json
├── docker-compose.yml
├── Dockerfile
├── README.md
└── .env.example
26. Interface plugin attendue
Chaque plugin doit exposer une interface standard.
Exemple conceptuel :
export interface TournamentPlugin {
  key: string;
  name: string;
  description: string;

  defaultSettings: Record<string, unknown>;

  validateScore(match, settings): ValidationResult;

  determineWinner(match, settings): ParticipantId | null;

  computeStanding(matches, participants, settings): Standing[];

  getScoreLabels(settings): ScoreLabels;

  getDisplayConfig(settings): DisplayConfig;
}
Le moteur principal doit appeler le plugin actif pour :
•	valider les scores ;
•	déterminer le vainqueur ;
•	calculer le classement ;
•	afficher les bons libellés ;
•	appliquer les règles spécifiques.
27. Roadmap
V1 — Socle fonctionnel
Objectif : organiser un tournoi simple de Mölkky.
Fonctionnalités :
•	Docker Compose fonctionnel ;
•	création tournoi ;
•	plugin générique ;
•	plugin Mölkky ;
•	ajout participants ;
•	génération poules ;
•	génération matchs ;
•	saisie scores ;
•	classement automatique ;
•	affichage TV simple.
V2 — Expérience tournoi
Fonctionnalités :
•	QR Code ;
•	vue joueur mobile ;
•	phases finales ;
•	podium ;
•	exports CSV ;
•	meilleure ergonomie admin.
V3 — Plateforme générique
Fonctionnalités :
•	système de plugins stabilisé ;
•	plugins pétanque, fléchettes, baby-foot ;
•	affichage TV avancé ;
•	statistiques ;
•	import/export ;
•	impressions ;
•	configuration avancée des règles.
V4 — Améliorations futures
Idées :
•	comptes utilisateurs ;
•	authentification admin ;
•	historique multi-tournois ;
•	mode cloud ;
•	WebSocket temps réel ;
•	thèmes visuels ;
•	API publique ;
•	plugins externes ;
•	marketplace de plugins.
28. Critères d’acceptation V1
La V1 est validée si :
•	l’application démarre avec Docker Compose ;
•	on peut créer un tournoi ;
•	on peut choisir le plugin Mölkky ;
•	on peut ajouter au moins 8 équipes ;
•	on peut générer deux poules de quatre équipes ;
•	les matchs sont générés automatiquement ;
•	les scores peuvent être saisis ;
•	les scores invalides sont refusés ;
•	le classement est calculé automatiquement ;
•	une vue TV affiche les matchs et classements ;
•	les données persistent après redémarrage.
29. Critères d’acceptation V3
La V3 est validée si :
•	plusieurs plugins sont disponibles ;
•	le moteur fonctionne sans dépendre d’un jeu précis ;
•	les règles de score sont propres à chaque plugin ;
•	les classements sont calculés selon les règles du plugin ;
•	les phases finales sont générées ;
•	la vue mobile fonctionne via QR Code ;
•	l’affichage TV est agréable et lisible ;
•	les exports sont disponibles ;
•	l’application reste utilisable localement sans Internet.
30. Priorités pour Codex
Commencer par une base simple, robuste et extensible.
Priorité absolue :
1.	créer la structure du projet ;
2.	rendre Docker Compose fonctionnel ;
3.	créer le modèle de données ;
4.	créer le moteur générique ;
5.	créer le système de plugin ;
6.	créer le plugin Mölkky ;
7.	créer l’interface admin minimale ;
8.	créer la génération des poules et matchs ;
9.	créer la saisie des scores ;
10.	créer le classement automatique ;
11.	créer la vue TV.
Ne pas chercher à tout faire dès le départ.
L’objectif est d’avoir une V1 solide, puis d’étendre progressivement vers la V3.
31. Demande finale à Codex
Créer l’application OpenTournament selon cette expression de besoin.
Le projet doit être pensé comme une plateforme générique de gestion de tournois avec plugins de règles.
La première implémentation doit inclure :
•	un moteur générique ;
•	un plugin Mölkky ;
•	un plugin générique ;
•	une interface admin ;
•	une vue TV ;
•	un déploiement Docker ;
•	une base SQLite persistante.
L’application doit être utilisable en local pour organiser un vrai tournoi de Mölkky.

