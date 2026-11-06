import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { RequireAuth } from './components/RequireAuth'
import { HomePage } from './pages/HomePage'
import { SupportPage } from './pages/SupportPage'
import { TenantCostsPage } from './pages/TenantCostsPage'
import { SourcesPage } from './pages/SourcesPage'
import { SourcesFormPage } from './pages/SourcesFormPage'
import { AgendaPage } from './pages/AgendaPage'
import { ArticlePage } from './pages/ArticlePage'
import { ArticleFeedbackPage } from './pages/ArticleFeedbackPage'
import { StrategyPage } from './pages/StrategyPage'
import { BrandArchitecturePage } from './pages/BrandArchitecturePage'
import { SearchPage } from './pages/SearchPage'
import { TargetsPage } from './pages/TargetsPage'
import { PlanningPage } from './pages/PlanningPage'
import { ThemePage } from './pages/ThemePage'
import { ResearchPage } from './pages/ResearchPage'
import { ArchivePage } from './pages/ArchivePage'
import { ArchiveArticlePage } from './pages/ArchiveArticlePage'
import { ArchivePostPage } from './pages/ArchivePostPage'
import { ArchivePostConfirmPage } from './pages/ArchivePostConfirmPage'
import { FastlanePage } from './pages/FastlanePage'
import { CampagnePage, CampagneRedirectToStap } from './pages/CampagnePage'
import { ReportPage } from './pages/ReportPage'
import { GalleryPage } from './pages/GalleryPage'
import { AccountPage } from './pages/AccountPage'
import { IntegrationsPage } from './pages/IntegrationsPage'
import { LoginPage } from './pages/LoginPage'
import { ForgotPasswordPage } from './pages/ForgotPasswordPage'
import { ResetPasswordPage } from './pages/ResetPasswordPage'
import { DesignSystemPage } from './pages/DesignSystemPage'
import { ReviewPage } from './pages/ReviewPage'
import { RequirePlatformAuth } from './components/RequirePlatformAuth'
import { PlatformLoginPage } from './pages/PlatformLoginPage'
import { PlatformForgotPasswordPage } from './pages/PlatformForgotPasswordPage'
import { PlatformResetPasswordPage } from './pages/PlatformResetPasswordPage'
import { PlatformLayout } from './pages/PlatformLayout'
import { PlatformOverviewView } from './pages/platform/PlatformOverviewView'
import { PlatformTenantView } from './pages/platform/PlatformTenantView'
import { PlatformCreateTenantView } from './pages/platform/PlatformCreateTenantView'
import { PlatformFinanceView } from './pages/platform/PlatformFinanceView'
import { PlatformSettingsView } from './pages/platform/PlatformSettingsView'
import { PlatformHygieneView } from './pages/platform/PlatformHygieneView'
import { SiteBranding } from './components/SiteBranding'

export default function App() {
  return (
    <BrowserRouter>
      <SiteBranding />
      <Routes>
        <Route path="/review/:token" element={<ReviewPage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/platform/login" element={<PlatformLoginPage />} />
        <Route path="/platform/forgot-password" element={<PlatformForgotPasswordPage />} />
        <Route path="/platform/reset-password/:token" element={<PlatformResetPasswordPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password/:token" element={<ResetPasswordPage />} />

        <Route element={<RequirePlatformAuth />}>
          <Route path="/platform" element={<PlatformLayout />}>
            <Route index element={<PlatformOverviewView />} />
            <Route path="tenant/new" element={<PlatformCreateTenantView />} />
            <Route path="tenant/:clientId" element={<PlatformTenantView />} />
            <Route path="finance" element={<PlatformFinanceView />} />
            <Route path="hygiene" element={<PlatformHygieneView />} />
            <Route path="settings" element={<PlatformSettingsView />} />
          </Route>
        </Route>

        <Route element={<RequireAuth />}>
          <Route path="/" element={<HomePage />} />
          <Route path="/brandarchitecture" element={<BrandArchitecturePage />} />
          <Route path="/styleguide" element={<StrategyPage />} />
          <Route path="/identity" element={<Navigate to="/brandarchitecture" replace />} />
          <Route path="/strategy" element={<Navigate to="/styleguide" replace />} />
          <Route path="/targets" element={<TargetsPage />} />
          <Route path="/planning" element={<PlanningPage />} />
          <Route path="/search" element={<SearchPage />} />
          <Route path="/sources" element={<SourcesPage />} />
          <Route path="/sources/new/:categorySlug" element={<SourcesFormPage />} />
          <Route path="/sources/:sourceId/edit" element={<SourcesFormPage />} />
          <Route path="/gallery" element={<GalleryPage />} />
          <Route path="/agenda" element={<AgendaPage />} />
          <Route path="/theme" element={<ThemePage />} />
          <Route path="/research" element={<ResearchPage />} />
          <Route path="/research/:slotId" element={<ResearchPage />} />
          <Route path="/article/feedback" element={<ArticleFeedbackPage />} />
          <Route path="/article/:slotId/feedback" element={<ArticleFeedbackPage />} />
          <Route path="/article/:slotId" element={<ArticlePage />} />
          <Route path="/article" element={<ArticlePage />} />
          <Route path="/archive" element={<ArchivePage />} />
          <Route path="/archive/:slotId/post" element={<ArchivePostPage />} />
          <Route path="/archive/:slotId/confirm" element={<ArchivePostConfirmPage />} />
          <Route path="/archive/:slotId" element={<ArchiveArticlePage />} />
          <Route path="/fastlane" element={<FastlanePage />} />
          <Route path="/campagnes" element={<CampagnePage />} />
          <Route path="/campagnes/nieuw" element={<Navigate to="/campagnes/nieuw/stap/1" replace />} />
          <Route path="/campagnes/nieuw/stap/:wizardStep" element={<CampagnePage />} />
          <Route
            path="/campagnes/:campaignId"
            element={<CampagneRedirectToStap defaultStep="7" />}
          />
          <Route path="/campagnes/:campaignId/stap/:wizardStep" element={<CampagnePage />} />
          <Route path="/campagne" element={<Navigate to="/campagnes" replace />} />
          <Route path="/report" element={<ReportPage />} />
          <Route path="/account" element={<AccountPage />} />
          <Route path="/integrations" element={<IntegrationsPage />} />
          <Route path="/support" element={<SupportPage />} />
          <Route path="/support/costs" element={<TenantCostsPage />} />
          <Route path="/design" element={<DesignSystemPage />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  )
}
