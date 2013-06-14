<?php
/**
 * Copyright (c) 11-Jun-2013 Dieter Raber <me@dieterraber.net>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to 
 * deal in the Software without restriction, including without limitation the 
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or 
 * sell copies of the Software, and to permit persons to whom the Software is 
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in 
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * DataCollector
 *
 * @author Dieter Raber <me@dieterraber.net>
 */
class DataCollector {
  
  /**
   * @var resource PDO database connection
   */
  protected $db = '';

  /**
   * Launcher
   * 
   * @param string $cldrPath path to local copy of CLDR repository, see readme.md for details
   * @param string $sqliteDbPath path to the SQLite database, can be created automatically
   * @param string $geoNamesUser geonames.org user name, see readme.md for details
   * @throws Exception
   */
  public function __construct($cldrPath, $sqliteDbPath, $geoNamesUser) {
    
    // check if cldr is installed
    $cldrPath .= '/common/main';
    if(!is_dir($cldrPath)){
      throw new Exception($cldrPath . ' does not exist. Get the latest SVN version from http://cldr.unicode.org');
    }

    // create database or make a backup in case it exists
    $sqliteDir = dirname($sqliteDbPath);
    if(!is_dir($sqliteDir)){        
      mkdir($sqliteDir, 0777, true);
    }
    if(is_file($sqliteDbPath)){   
      $backup = $sqliteDir . '/' . date('Y-m-d-H-i-s', filemtime($sqliteDbPath)) . '-' . basename($sqliteDbPath);  
      copy($sqliteDbPath, $backup);
    }
    touch($sqliteDbPath);
    
    // connect to database
    $this -> db = new PDO('sqlite:' . str_replace(DIRECTORY_SEPARATOR, '/', $sqliteDbPath));
    $this -> db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // collect data from cldr
    $this -> proceedCldrData($cldrPath);

    $this -> mapCountryContinent($geoNamesUser);
  }
  
  
  /**
   * Retrieve data from the CLDR XML files
   * 
   * @param string $cldrPath
   */
  protected function proceedCldrData($cldrPath) {         

    /* map M.49 area codes to ISO 3166-1 */
    $continentMapping = [
      '002' => 'AF',
      '003' => 'NA',
      '005' => 'SA',
      '009' => 'OC',
      '142' => 'AS',
      '150' => 'EU'
    ];      

    $codes = [
      'country' => [],
      'continent' => []
    ];

    foreach($codes as $type => $value){
      // recreate tables
      $stm = $this -> db -> prepare(sprintf('DROP TABLE IF EXISTS %s_names_i18n', $type));
      $stm -> execute();
      $stm = $this -> db -> prepare(sprintf('CREATE TABLE %s_names_i18n (code VARCHAR NOT NULL, lang VARCHAR NOT NULL, value, PRIMARY KEY (code, lang))', $type));
      $stm -> execute();
    } 

    $langs = [];
    $this -> db -> beginTransaction();
    foreach(glob($cldrPath . '/*.xml') as $countryXml) {
      $lang = strtok(basename($countryXml), '_.');
      if(in_array($lang, $langs)){
        continue;
      }

      $xml  = simplexml_load_file($countryXml);
      $territories = $xml -> localeDisplayNames -> territories;
      if(is_null($territories)) {
        continue;
      }
      if(is_null($territories -> territory)) {
        continue;
      }
      if(count((array)$territories -> territory) < 200){
        continue;
      }
      $langs[] = $lang;

      foreach($territories -> territory as $territory) {
        $code  = (string)$territory -> attributes() -> type;
        $draft = (string)$territory -> attributes() -> draft;
        // ZZ stands for 'Unknown or Invalid Region' FX for 'Metropolitan France'
        if(in_array($code, ['ZZ', 'FX'])) {
          continue;
        }
        // these are M.49 area codes and only interesting for continents
        if(is_numeric($code)) {
          if(isset($continentMapping[$code])) {
            $codes['continent'][$continentMapping[$code]] = (string)$territory;
          }
          continue;
        }
        if($draft === 'unconfirmed'){
          continue;
        }
        if(empty($codes['country'][$code]) || $draft === 'short'){          
          $codes['country'][$code] = (string)$territory;
        }
      }
      foreach($codes as $type => $data) {
        $stm = $this -> db -> prepare(sprintf('INSERT INTO %s_names_i18n VALUES (:code, :lang, :value)', $type));
        foreach($data as $code => $value) {
          $stm -> execute(['code' => $code, 'lang' => $lang, 'value' => $value]);
        }      
      }
      $codes = [
        'country' => [],
        'continent' => []
      ];
    }
    $this -> db -> commit();
  }

  /**
   * Retrieve data from the geonames.org web service
   * 
   * @param string $geoNamesUser - your geoname.org user name, see readme.md for details
   */
  protected function mapCountryContinent($geoNamesUser){ 

    $stm = $this -> db -> prepare('DROP TABLE IF EXISTS country_continent_map');
    $stm -> execute();

    $stm = $this -> db -> prepare('CREATE TABLE country_continent_map (country_code VARCHAR NOT NULL, continent_code VARCHAR NOT NULL, PRIMARY KEY (country_code, continent_code))');
    $stm -> execute();

    $ch = curl_init(sprintf('http://api.geonames.org/countryInfoJSON?username=%s', $geoNamesUser));
 
    // Configuring curl options
    $options = array(
      CURLOPT_RETURNTRANSFER => true
    );

    // Setting curl options
    curl_setopt_array($ch, $options);

    // Getting results
    $result =  curl_exec($ch); // Getting jSON result string
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($status === 200) {

      $stm = $this -> db -> prepare('INSERT INTO country_continent_map VALUES (:country_code, :continent_code)');
      $this -> db -> beginTransaction();

      foreach(json_decode($result, true) as $country){
        foreach($country as $data){
          $stm -> execute(['country_code' => $data['countryCode'], 'continent_code' => $data['continent']]);
        }
      }

      $this -> db -> commit();
      
    }

  }
}
