import csv
import time
import sys
# Important : vous devrez peut-être installer geopy : pip install geopy
try:
    from geopy.geocoders import Nominatim
    from geopy.exc import GeocoderTimedOut, GeocoderServiceError
except ImportError:
    print("Erreur : La librairie 'geopy' est manquante. Installez-la avec 'pip install geopy'")
    sys.exit(1)

INPUT_FILE = "import_electoral_data.csv"
OUTPUT_FILE = "import_electoral_data_geocoded.csv"
USER_AGENT = "sure-vote-benin-geocoding"

def get_coordinates(geolocator, query, attempt=1, max_attempts=3):
    try:
        location = geolocator.geocode(query, timeout=10)
        if location:
            return location.latitude, location.longitude
    except (GeocoderTimedOut, GeocoderServiceError):
        if attempt <= max_attempts:
            time.sleep(2 * attempt)
            return get_coordinates(geolocator, query, attempt + 1)
    return None, None

def process_geocoding():
    geolocator = Nominatim(user_agent=USER_AGENT)
    
    unique_centres = {} # Cache pour éviter de requêter le même centre plusieurs fois (car plusieurs postes)

    try:
        with open(INPUT_FILE, 'r', encoding='utf-8') as f_in, \
             open(OUTPUT_FILE, 'w', newline='', encoding='utf-8') as f_out:
            
            reader = csv.DictReader(f_in, delimiter=';')
            fieldnames = reader.fieldnames + ['LATITUDE', 'LONGITUDE']
            writer = csv.DictWriter(f_out, fieldnames=fieldnames, delimiter=';')
            writer.writeheader()
            
            count = 0
            success = 0
            
            print("Début du géocodage...")
            
            for row in reader:
                count += 1
                
                # Construction de la clé unique du lieu (plus large que le centre)
                # On suppose que tous les centres d'un même village/quartier sont proches
                commune = row.get('COMMUNE', '').strip()
                arrondissement = row.get('ARRONDISSEMENT', '').strip()
                village = row.get('VILLAGE_QUARTIER', '').strip()
                nom_centre = row.get('CENTRE DE VOTE', '').strip()
                
                # Clé pour le cache : on cache par LOCALITÉ, pas par centre
                # Si on a déjà les coordonnées de ce village, on les utilise pour tous les centres du village
                # (Sauf si on veut vrmt la précision du centre, mais souvent introuvable sur OSM)
                location_key = f"{village}-{arrondissement}-{commune}"
                
                lat, lon = None, None
                
                if location_key in unique_centres:
                    lat, lon = unique_centres[location_key]
                else:
                    # Stratégie de repli : Village > Arrondissement > Commune
                    queries = []
                    
                    if village:
                        queries.append(f"{village}, {arrondissement}, {commune}, Bénin")
                        queries.append(f"{village}, {commune}, Bénin")
                    
                    if arrondissement:
                         queries.append(f"{arrondissement}, {commune}, Bénin")
                    
                    if commune:
                        queries.append(f"{commune}, Bénin")
                    
                    found = False
                    for query in queries:
                        print(f"Recherche ({count}): {query}...")
                        lat, lon = get_coordinates(geolocator, query)
                        if lat and lon:
                            success += 1
                            print(f"   -> Trouvé : {lat}, {lon}")
                            found = True
                            break
                        time.sleep(1.1) # Respecter la politique de Nominatim (1 req/sec)
                    
                    # On sauvegarde le résultat (même si None) pour ne pas re-chercher pour ce village
                    unique_centres[location_key] = (lat, lon)
                    if not found:
                         print("   -> Non trouvé.")
                
                row['LATITUDE'] = lat if lat else ''
                row['LONGITUDE'] = lon if lon else ''
                
                writer.writerow(row)
                
                if count % 10 == 0:
                    f_out.flush()

        print(f"Terminé ! {success} adresses trouvées sur {len(unique_centres)} centres uniques.")

    except FileNotFoundError:
        print(f"Erreur : Le fichier {INPUT_FILE} est introuvable.")

if __name__ == "__main__":
    process_geocoding()
