# Cope postal - Ville
Allow to use a multi text question to get Postal code, town name and Insee code. User have an autocomplete helper to search the town (only french town actually).

## Installation

### Via GIT
- Go to your LimeSurvey Directory (version up to 2.05)
- Clone in plugins/cpVille directory

### Via ZIP dowload
- Get the file at <http://extensions.sondages.pro/IMG/auto/cpVille.zip> and uncompress it
- Move the files included to plugins/cpVille directory

## Setup and activation
- During activation an new table is created and data is set up in this table.
- If your hoster have very limited time for PHP scriot : this can break.
- You can set yourself the DB : name must be lime_cpville_insee_cp (lime_ must be replaced by real prefix) and column insee, nom, cp, nomsimple.
- You can add another column : then this can prefill specific subquestion.
- If needed : you can update the database your way.

## Documentation
In a multi text question type : set a Sub question with SaisieVille code launch a system to
- Allow user search for postal code or part of town name to fill the sub question
- Fill Extra sub question with code CodePostal with the postal code
- Fill Extra sub question with code Insee with the Insee code
- Fill Extra sub question with code Nom with the town name

Each code can be completed by some cararcter : this allow multiple SaisieVille in same question. For example SaisieVilleA, SaisieVilleB and InseeA, InseeB filled by insee code.
Plugin settings allow admin user to
- Update the default sub question code and use awn code
- Choose the maximum number of lines returned
- Choose if extra sub question is shown or not
- Choose what to show to user and what to save in the database of LimeSurvey

For user, some helper is done
- Search on postal code if only number is entered
- Search on multiple part : example `villen asc` for Villeneuve d'Ascq

## Home page & Copyright

### Code
- HomePage <http://extensions.sondages.pro/cpville>
- Copyright © 2015 Observatoire Régional de la Santé (ORS) - Nord-Pas-de-Calais <http://www.orsnpdc.org/>
- copyright © 2016 Formations logiciels libres - 2i2l = 42 <http://2i2l.fr/>
- Copyright © 2015-2016 Denis Chenu <http://sondages.pro>
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
