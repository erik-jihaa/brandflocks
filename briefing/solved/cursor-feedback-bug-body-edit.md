# Bug fix: feedback-pagina — body copy edit opent write article-scherm

## Het probleem

Op de feedback-pagina (`/article/{id}/feedback`, Grid #3) werkt de header-correctie
correct binnen de feedback-vormgeving. Maar zodra de redacteur de **body copy** wil
aanpassen, springt de UI uit de feedback-vormgeving naar de write article-vormgeving
(Grid #2 / ArticlePage). Dat mag niet — de redacteur moet de body copy kunnen
bewerken zonder het feedback-scherm te verlaten.

---

## Oorzaak (diagnose)

Zoek naar één van deze patronen in de codebase:

**Patroon A — verkeerde routing:**
```tsx
// In FeedbackPage of een component daarop:
if (editingBody) {
  navigate(`/article/${id}`);  // ← fout: stuurt naar ArticlePage
}
```

**Patroon B — conditional render die de verkeerde component toont:**
```tsx
// FeedbackPage rendert ArticlePage of ArticleEditor als body in edit-state gaat:
{editingField === 'content' && <ArticlePage ... />}
// of:
{editingField === 'content' && <ArticleEditor ... />}
```

**Patroon C — gedeeld component met interne routing:**
Een gedeeld `ArticleBodyEditor`-component detecteert dat `status !== 'feedback'`
en rendert zichzelf in "write"-modus in plaats van "feedback"-modus.

---

## De fix

De feedback-pagina moet **zelfstandig** blijven — de body copy is inline bewerkbaar
binnen Grid #3, zonder ooit naar Grid #2 te navigeren of Grid #2-componenten
te renderen.

### Wat wel mag op de feedback-pagina:
- Body copy direct inline editen (textarea in dezelfde layout als Grid #3)
- Feedback-kaarten (okay | delete) naast de tekst zichtbaar houden
- Header editen (werkt al correct — gebruik hetzelfde patroon voor body)

### Wat niet mag:
- `navigate('/article/...')` aanroepen vanuit de feedback-pagina
- `<ArticlePage>` of `<ArticleEditor>` renderen als subcomponent binnen de feedback-pagina
- De Grid #2-layout (linker meta-kolom + hoofdtekst zonder feedback-kaarten) tonen

### Concrete fix:

1. Zoek waar body copy editing wordt getriggerd op de feedback-pagina.
2. Vervang elke `navigate` of component-wissel door **dezelfde aanpak als de header**:
   inline edit binnen de bestaande feedback-layout.
3. Als de body-editor een apart component is dat intern navigeert of de layout
   wisselt, geef het dan een `mode="feedback"` prop waarmee het die navigatie
   onderdrukt en inline blijft.

---

## Visuele referentie

**Feedback-pagina (Grid #3) — correct:**
```
[Linker kolom: meta + feedback-kaarten]  [Body: inline bewerkbaar textarea]
                                          [okay | delete knoppen bij elke suggestie]
```

**Wat er nu fout gaat:**
```
[Linker kolom: meta + feedback-kaarten]  → navigeert naar →  [Write article (Grid #2)]
```

Header gebruikt al het juiste patroon — body moet identiek werken.

---

## Checklist

- [ ] Oorzaak gevonden (patroon A, B of C hierboven)
- [ ] Body copy inline bewerkbaar binnen Grid #3, geen navigate() aanroep
- [ ] Feedback-kaarten blijven zichtbaar naast de body tijdens het bewerken
- [ ] Na edit: body opslaan en terugkeren naar feedback-overzichtsstate (niet naar ArticlePage)
- [ ] Header-edit werkt nog steeds correct (regressiecheck)
- [ ] Geen nieuwe navigatie naar `/article/{id}` vanuit de feedback-pagina
