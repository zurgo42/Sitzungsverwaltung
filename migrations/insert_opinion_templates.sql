-- Template-Daten für Meinungsbild-Tool
-- Erstellt: 2025-11-18

INSERT INTO opinion_answer_templates (template_id, template_name, description, option_1, option_2, option_3, option_4, option_5, option_6, option_7, option_8, option_9, option_10) VALUES
(1, '3er: Ja/Nein/Enthaltung', 'Die klassische Ja-Nein-Enthaltung-Variante', 'Ja', 'Nein', 'Enthaltung', 'Weiß nicht', 'Ich möchte das nicht beantworten', NULL, NULL, NULL, NULL, NULL),

(2, '5er: Passt-Skala', 'Passt sehr gut bis passt gar nicht', 'Passt sehr gut', 'Passt', 'Geht so', 'Passt eher nicht', 'Passt gar nicht', 'Unentschieden', 'Weiß nicht', 'Ich möchte das nicht beantworten', NULL, NULL),

(3, '5er: Dafür/Dagegen', 'Dafür bis dagegen', 'Unbedingt dafür', 'Dafür', 'Indifferent', 'Dagegen', 'Unbedingt dagegen', 'Unentschieden', 'Weiß nicht', 'Ich möchte das nicht beantworten', NULL, NULL),

(4, '5er: Gefällt mir', 'Gefällt mir sehr gut bis überhaupt nicht', 'Gefällt mir sehr gut', 'Gefällt mir', 'Indifferent', 'Gefällt mir eher nicht', 'Gefällt mir überhaupt nicht', 'Unentschieden', 'Weiß nicht', 'Ich möchte das nicht beantworten', NULL, NULL),

(5, 'Skala 1-9', 'Zustimmung ... auf einer Skala von 1 bis 9', 'Skala 1', 'Skala 2', 'Skala 3', 'Skala 4', 'Skala 5', 'Skala 6', 'Skala 7', 'Skala 8', 'Skala 9', 'Ich möchte das nicht beantworten'),

(6, 'Dringlichkeit', 'Zeitlich: Dringlichkeit bis Nicht machen', 'Sofort!', 'Macht es möglich!', 'Bald umsetzen', 'Gern, bei Gelegenheit', 'Naja, wenn gerade Zeit ist ...', 'Da ist anderes wirklich wichtiger', 'Das hält uns bloß von Wichtigerem ab', 'Nee, das am besten überhaupt nicht.', 'Weiß nicht', 'Ich möchte das nicht beantworten'),

(7, 'Wichtigkeit', 'Wichtig bis Zeitverschwendung', 'unabdingbar', 'sehr wichtig', 'wichtig', 'weniger wichtig', 'egal', 'ziemlich unwichtig', 'besser sein lassen', 'Auf gar keinen Fall!', 'Weiß nicht', 'Ich möchte das nicht beantworten'),

(8, 'Wünsche', 'Wünsche mir sehr bis auf keinen Fall', 'Sehr!', 'Ja', 'Gern', 'Ok', 'Naja', 'Nicht so', 'Nein', 'Auf keinen Fall!', 'Weiß nicht', 'Ich möchte das nicht beantworten'),

(9, 'Häufigkeit', 'immer bis nie', 'immer!', 'meistens', 'oft', 'gelegentlich', 'zufällig mal', 'nur zufällig', 'fast nie', 'nie!', 'Weiß nicht', 'Ich möchte das nicht beantworten'),

(10, 'Priorität', 'Vordringlich oder nicht', 'Absolutes Muss!', 'Klare Priorität', 'Vordringlich', 'Erledigen, ist wohl wichtig', 'Sollten wir mal machen', 'Eher nicht', 'Nein, lassen wir besser sein', 'Auf keinen Fall!', 'Weiß nicht', 'Ich möchte das nicht beantworten'),

(11, 'Frei', 'Leeres Template für eigene Optionen', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),

(12, 'Nützlichkeit', 'Zu Frage 2', 'Jaa - das fehlte, dringend!', 'Finde ich sehr nützlich', 'Eine nette Ergänzung', 'Wenn es da ist, meinetwegen', 'Halte ich nicht für erforderlich', 'Überflüssig', 'Schädlich', 'Am besten das ganze Tool einstampfen', 'Weiß nicht', 'Ich möchte das nicht beantworten'),

(13, 'Bewertung', 'Bewertung von langweilig bis spannend', 'langweilig', 'Zeitvertreib', 'spannend', NULL, NULL, NULL, NULL, NULL, 'Ich möchte das nicht beantworten', NULL);
