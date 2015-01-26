Rentman WooCommerce plugin

Vereisten:
 - Wordpress 4.0+
 - WooCommerce 2.2+
 Oudere versies kunnen werken, maar zijn niet getest. 

Hoe te installeren: 
      (let op, de volgorde is belangrijk)
 1 Unzip het zipbestand in de wordpress/wp-content/plugins map op de server
 2 Zorg ervoor dat u ingelogd bent als beheerder (admin) en ga naar het beheerderspaneel
 3 Ga naar het 'plugins' scherm en activeer de Rentman plugin
 4 De plugin is nu geactiveerd. Ga naar WooCommerce -> Rentman om de koppeling in te stellen.
 5 Vul ALS EERSTE uw API gegevens in bij de inlogvelden en druk op 'Wijzigingen opslaan'
 6 Controleer of boven de inlogvelden 'Logingegevens correct' staat. 
 	Zo niet, controleer uw gegevens en herhaal stap 5 totdat de inloggegevens correct zijn.
 7 Wanneer de inloggegevens correct zijn, klikt u op de knop 'Producten importeren', eveneens op het Rentman scherm in WooCommerce. 
 8 De gegevens worden nu opgehaald. Dit kan enkele minuten duren. Tijdens het importeren geeft de browser aan dat hij aan het laden	 	is. Dit is normaal. U kunt het importeren niet onderbreken.
 9 Uw Rentmanproducten zijn nu beschikbaar in uw webshop.

Belangrijk:

Wanneer er van API wordt gewisseld kunnen er conflicten ontstaan tussen categorieën met dezelfde Rentman ID. Om dit te voorkomen moet u VOORDAT de producten voor het eerst worden geïmporteerd op de nieuwe account en nadat ze voor het laatst zijn geïmporteerd op de oude account er een wijziging in de Database gemaakt worden. In het tabel 'wp_options' is een entry met als 'option_name' 'rentman_categories'. Verander de 'option_value' in 'a:0:{}', zo kunnen er geen conflicten ontstaan.