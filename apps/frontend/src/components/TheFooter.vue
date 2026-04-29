<script setup lang="ts">
import { ref } from 'vue';

const showImpressum = ref(false);
</script>

<template>
  <footer class="app-footer">
    <div class="footer-content">
      <div class="footer-section">
        <h4 class="footer-title">Phoenix Athletics</h4>
        <p class="footer-text">
          Visit our official website for information on
          <a href="https://phoenix-athletics.de/" target="_blank" rel="noopener">Satzung, Beitragsordnung</a> and more.
        </p>
        <a href="https://phoenix-athletics.de/" target="_blank" rel="noopener" class="footer-link">
          phoenix-athletics.de <i class="pi pi-external-link"></i>
        </a>
      </div>

      <div class="footer-section">
        <h4 class="footer-title">Legal</h4>
        <ul class="footer-list">
          <li>
            <a href="/datenschutz.pdf" download class="footer-link">
              <i class="pi pi-download"></i> Datenschutz (Privacy Policy)
            </a>
          </li>
          <li>
            <a href="javascript:void(0)" @click="showImpressum = true" class="footer-link">
              <i class="pi pi-info-circle"></i> Impressum (Legal Notice)
            </a>
          </li>
        </ul>
      </div>

      <div class="footer-section">
        <h4 class="footer-title">Hosting</h4>
        <p class="footer-text">
          This App is hosted by
          <a href="https://codingcube.de/" target="_blank" rel="noopener" class="footer-link highlight">codingcube</a>
        </p>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; {{ new Date().getFullYear() }} Phoenix Athletics. All rights reserved.</p>
    </div>

    <Dialog v-model:visible="showImpressum" header="Impressum" :modal="true" class="w-full max-w-2xl">
      <div class="impressum-content">
        <section>
          <h3 class="primary-text">Angaben gemäß § 5 TMG</h3>
          <p>
            Kubilay Anil<br />
            Kreuzstr.<br />
            89160 Dornstadt<br />
            Germany
          </p>
        </section>

        <section class="mt-4">
          <h3 class="primary-text">Kontakt</h3>
          <p>
            Telefon: +49 (0) 162 7895106<br />
            E-Mail: <a href="mailto:kubilay.anil@codingcube.de">kubilay.anil@codingcube.de</a>
          </p>
        </section>

        <section class="mt-4">
          <h3 class="primary-text">Haftung für Inhalte</h3>
          <p>
            Als Diensteanbieter sind wir gemäß § 7 Abs.1 DDG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen.
          </p>
        </section>

        <section class="mt-4">
          <h3 class="primary-text">Urheberrecht</h3>
          <p>
            Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.
          </p>
        </section>
      </div>
      <template #footer>
        <Button severity="primary" label="Close" icon="pi pi-check" @click="showImpressum = false" autofocus />
      </template>
    </Dialog>
  </footer>
</template>

<style scoped lang="scss">
.app-footer {
  background-color: var(--bg-primary-color);
  color: white;
  padding: 4rem 2rem 2rem;
  margin-top: 4rem;
  border-top: 4px solid var(--primary-color);
}

.footer-content {
  max-width: 1400px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 3rem;
  margin-bottom: 3rem;
}

.footer-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.footer-title {
  color: var(--primary-color);
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0;
}

.footer-text {
  font-size: 0.95rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.7);
  margin: 0;

  a {
    color: white;
    text-decoration: underline;
    &:hover {
      color: var(--primary-color);
    }
  }
}

.footer-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.footer-link {
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  font-size: 0.95rem;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:hover {
    color: var(--primary-color);
    transform: translateX(4px);
  }

  &.highlight {
    font-weight: 700;
    color: var(--primary-color);
    text-decoration: none;
    &:hover {
      text-decoration: underline;
    }
  }
}

.footer-bottom {
  max-width: 1400px;
  margin: 0 auto;
  padding-top: 2rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  text-align: center;
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.85rem;
}

.impressum-content {
  color: white;
  line-height: 1.6;

  h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
  }

  p {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 1rem;
  }

  a {
    color: var(--primary-color);
    text-decoration: none;
    &:hover {
      text-decoration: underline;
    }
  }
}

@media (max-width: 768px) {
  .app-footer {
    padding: 3rem 1.5rem 1.5rem;
  }

  .footer-content {
    gap: 2rem;
  }
}
</style>
