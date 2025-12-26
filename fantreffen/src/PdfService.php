<?php
/**
 * PdfService - PDF-Generierung für Faltblatt und Einladungsbogen
 */

class PdfService {
    private string $fpdfPath;
    private string $outputPath;
    private string $imagePath;

    public function __construct() {
        $this->fpdfPath = __DIR__ . '/../../fpdf';
        $this->outputPath = __DIR__ . '/../pdf';
        $this->imagePath = __DIR__ . '/../public/images';

        // FPDF laden
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $this->fpdfPath . '/font/');
        }

        if (!class_exists('FPDF')) {
            require_once $this->fpdfPath . '/fpdf.php';
        }

        // Output-Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Prüft ob FPDF verfügbar ist
     */
    public function isAvailable(): bool {
        return class_exists('FPDF') || file_exists($this->fpdfPath . '/fpdf.php');
    }

    /**
     * Generiert alle PDFs für eine Reise
     */
    public function generateForReise(array $reise): array {
        $results = [
            'faltblatt' => false,
            'einladung' => false,
            'errors' => []
        ];

        try {
            $results['faltblatt'] = $this->generateFaltblatt($reise);
        } catch (Exception $e) {
            $results['errors'][] = 'Faltblatt: ' . $e->getMessage();
        }

        try {
            $results['einladung'] = $this->generateEinladung($reise);
        } catch (Exception $e) {
            $results['errors'][] = 'Einladung: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Generiert das Faltblatt (Schiff zum Falten)
     */
    public function generateFaltblatt(array $reise): bool {
        if (!class_exists('FPDF')) {
            throw new Exception('FPDF nicht verfügbar');
        }

        $reiseId = $reise['reise_id'];
        $reiseText = 'Reise ' . $reise['schiff'] . ' vom ' .
                     date('d.m.Y', strtotime($reise['anfang'])) . ' - ' .
                     date('d.m.Y', strtotime($reise['ende']));

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();

        // Linienfarbe auf Blau einstellen
        $pdf->SetDrawColor(0, 0, 255);
        $pdf->SetTextColor(42, 89, 129);
        $pdf->SetLineWidth(0.1);

        // Rahmenlinien
        $pdf->Line(25, 8, 185, 8);
        $pdf->Line(25, 290, 185, 290);

        // Bilder (falls vorhanden)
        $aidaschlange = $this->imagePath . '/Aidaschlange.jpg';
        $aidakopf = $this->imagePath . '/Aidaschlange_kopfstehend.jpg';

        if (file_exists($aidaschlange)) {
            $pdf->Image($aidaschlange, 53, 62, 140, 13, 'jpg');
        }
        if (file_exists($aidakopf)) {
            $pdf->Image($aidakopf, 55, 223, 140, 13, 'jpg');
        }

        // Texte
        $pdf->SetXY(40, 60);
        $pdf->SetFont('Arial', '', 30);
        $pdf->Text(25, 50, 'Fantreffen');

        $pdf->SetFont('Arial', '', 18);
        $pdf->Text(60, 58, $reiseText);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Text(50, 110, "Diese Seite nach innen falten, dann rumdrehen");
        $pdf->Text(50, 116, "Dach falten, untere Raender hochklappen");
        $pdf->Text(50, 122, "wie dort beschrieben falten, dann rechts/links nach vorn/hinten falten");
        $pdf->Text(50, 128, "entstehende Tasche aufziehen, Bug und Heck hochklappen, dann auseinanderziehen");
        $pdf->Text(50, 134, "an den vorgeknickten Kanten die Seiten hochziehen");

        $pdf->Text(50, 150, "Copyright Logo: Aida");
        $pdf->Text(50, 156, "Gestaltung: Familienkreuzfahrten - https://alle-an-bord.de");
        $pdf->Text(50, 170, "erstellt mit https://aidafantreffen.de");
        $pdf->Text(25, 7, "Segel beiderseits oberhalb der Papierkante anknicken, dann an der Linie nach unten knicken");

        $outputFile = $this->outputPath . '/Schiff' . $reiseId . '.pdf';
        $pdf->Output('F', $outputFile);

        return file_exists($outputFile);
    }

    /**
     * Generiert den Einladungsbogen
     */
    public function generateEinladung(array $reise): bool {
        if (!class_exists('FPDF')) {
            throw new Exception('FPDF nicht verfügbar');
        }

        $reiseId = $reise['reise_id'];
        $reiseText = $reise['schiff'] . ' vom ' .
                     date('d.m.Y', strtotime($reise['anfang'])) . ' - ' .
                     date('d.m.Y', strtotime($reise['ende']));

        // Schiffsbild suchen
        $schiffLower = strtolower($reise['schiff']);
        $foto = $this->imagePath . '/' . $schiffLower . '.jpg';

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();

        // Linienfarbe auf Blau einstellen
        $pdf->SetDrawColor(0, 0, 255);
        $pdf->SetTextColor(42, 89, 129);
        $pdf->SetLineWidth(0.1);

        // Schiffsbild (falls vorhanden)
        if (file_exists($foto)) {
            $pdf->Image($foto, 25, 12, 100, 25, 'jpg');
        }

        // Texte
        $pdf->SetXY(40, 100);
        $pdf->SetFont('Arial', '', 30);
        $pdf->Text(25, 55, 'Einladung zum Fantreffen');

        $pdf->SetFont('Arial', '', 18);
        $pdf->Text(25, 62, "Reise " . $reiseText);

        $outputFile = $this->outputPath . '/Bogen' . $reiseId . '.pdf';
        $pdf->Output('F', $outputFile);

        return file_exists($outputFile);
    }

    /**
     * Prüft ob PDFs für eine Reise existieren
     */
    public function existsForReise(int $reiseId): array {
        return [
            'faltblatt' => file_exists($this->outputPath . '/Schiff' . $reiseId . '.pdf'),
            'einladung' => file_exists($this->outputPath . '/Bogen' . $reiseId . '.pdf')
        ];
    }

    /**
     * Gibt den Pfad zum Faltblatt zurück
     */
    public function getFaltblattPath(int $reiseId): ?string {
        $path = $this->outputPath . '/Schiff' . $reiseId . '.pdf';
        return file_exists($path) ? $path : null;
    }

    /**
     * Gibt den Pfad zum Einladungsbogen zurück
     */
    public function getEinladungPath(int $reiseId): ?string {
        $path = $this->outputPath . '/Bogen' . $reiseId . '.pdf';
        return file_exists($path) ? $path : null;
    }

    /**
     * Gibt die Download-URL für ein PDF zurück
     */
    public function getDownloadUrl(int $reiseId, string $type): string {
        return "pdf-download.php?id={$reiseId}&type={$type}";
    }

    /**
     * Löscht PDFs für eine Reise
     */
    public function deleteForReise(int $reiseId): void {
        $faltblatt = $this->outputPath . '/Schiff' . $reiseId . '.pdf';
        $einladung = $this->outputPath . '/Bogen' . $reiseId . '.pdf';

        if (file_exists($faltblatt)) unlink($faltblatt);
        if (file_exists($einladung)) unlink($einladung);
    }
}
