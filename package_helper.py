import zipfile
import os
import sys

def zip_directory(folder_path, output_path):
    with zipfile.ZipFile(output_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(folder_path):
            for file in files:
                file_path = os.path.join(root, file)
                # Create a relative path for the file in the zip
                rel_path = os.path.relpath(file_path, folder_path)
                zipf.write(file_path, rel_path)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python3 package.py <folder_path> <output_path>")
        sys.exit(1)
    
    zip_directory(sys.argv[1], sys.argv[2])
    print(f"Successfully packaged {sys.argv[1]} to {sys.argv[2]}")
