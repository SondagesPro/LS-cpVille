# Cope postal - Ville
Allows the use of multi text questions to get a Postal code, town name and Insee code. Users have an autocomplete helper to search for the town (only French town at the moment).

## Installation

### Via GIT
- Go to your LimeSurvey Directory (version up to 2.05)
- Clone in the plugins/cpVille directory

### Via ZIP dowload
- Get the file at <http://extensions.sondages.pro/IMG/auto/cpVille.zip> and uncompress it
- Move the files included to the plugins/cpVille directory

## Setup and activation
- During activation a new table is created and data is set up in this table.
- If your host has very little time allowed for PHP scripts : you can send yourself the DB
- You can set up the DB table yourself : its name must be lime_cpville_insee_cp (lime_ must be replaced by your prefix) and have the columns "insee", "nom", "cp", and "nomsimple".
- You can add another column : this can prefill specific subquestions with the same code.
- If needed : you can update the database your way.

## Documentation
In a multi text question type : set up a Sub question with the code "SaisieVille" and it will automatically launch a system to
- Allow users to search for postal codes or part of town name to fill in the sub question
- Fill an Extra sub question whose code is "CodePostal" with the postal code
- Fill an Extra sub question whose code is "Insee" with the Insee code
- Fill an Extra sub question whose code is "Nom" with the town name
- Fill an Extra sub question whose code is a column name of the DB table (you can test with "region" or "departement")

Plugin settings allow admin users to 
- Update the default sub question code and use awn code
- Choose the maximum number of lines returned
- Choose if extra sub questions are shown or not
- Choose what to show to users and what to save in the database of LimeSurvey

For users, some help is provided
- Search on postal code if only numbers are entered
- Search on multiple parts : example villen asc

## Home page & Copyright

### Code
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2015-2018 Denis Chenu <http://sondages.pro>
- Copyright © 2015 Observatoire Régional de la Santé (ORS) - Nord-Pas-de-Calais <http://www.orsnpdc.org/>
- Copyright © 2016 Formations logiciels libres - 2i2l = 42 <http://2i2l.fr/>
- Copyright © 2016 Comité Régional du Tourisme de Bretagne <https://www.tourismebretagne.com/>
- Licence : GNU General Public License <https://www.gnu.org/licenses/gpl-3.0.html>

### Data
- Insee, Recensements de la population
  - Copyright © Insee <http://www.insee.fr>
  - Licence : Licence Ouverte / Open Licence <https://www.data.gouv.fr/fr/datasets/population/>
  - Date : 15-10-04
- Base officielle des codes postaux
  - Copyright © Le groupe La poste <http://legroupe.laposte.fr/>
  - Licence : Licence Ouverte / Open Licence <https://www.data.gouv.fr/fr/datasets/base-officielle-des-codes-postaux/>
  - Date : 15-10-04
- Adaptation ( Add population to "Base officielle des codes postaux", replace ST and STE)
  - Copyright © 2015 Denis Chenu <http://sondages.pro>
  - Licence : ODbL <http://opendatacommons.org/licenses/odbl/>
