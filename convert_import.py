
import re
import csv

input_file = "~/Téléchargements/611556068-Benin-Legislatives-2023-Liste-Centres-Et-Postes-de-Vote.txt"
output_file = "./import_electoral_data.csv"

def is_valid_row(parts):
    if len(parts) != 7:
        return False
    # Vérifier si le premier champ est un nombre
    if not parts[0].isdigit():
        return False
    return True

def convert():
    with open(input_file, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    rows = []
    
    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue
            
        # diviser la ligne en champs en utilisant 2 ou plus espaces comme séparateur
        parts = re.split(r'\s\s+', stripped)
        
        if is_valid_row(parts):
            rows.append(parts)
            
    # Ecrire dans un fichier CSV
    with open(output_file, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f, delimiter=';')
        writer.writerow(['N°', 'DEPARTEMENT', 'COMMUNE', 'ARRONDISSEMENT', 'VILLAGE_QUARTIER', 'CENTRE DE VOTE', 'POSTE DE VOTE'])
        writer.writerows(rows)
        
    print(f"Conversion réussie : {len(rows)} lignes converties en CSV : {output_file}")

if __name__ == "__main__":
    convert()
