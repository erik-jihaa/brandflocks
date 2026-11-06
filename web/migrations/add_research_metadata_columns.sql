-- Registry-migratie: stance/reliability kolommen worden aangemaakt door
-- add_research_stance_reliability.sql (zelfde kolommen, later in alfabetische volgorde).
-- Op bestaande DB's stonden de kolommen soms al via auto-heal vóór deze registry-regel.
SELECT 1 AS research_metadata_columns_ok;
