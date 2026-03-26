<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: tr
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n > 1);
',

  // Config labels
  'Help Topic' => 'Yardım Konusu',
  'Start Button Label' => 'Başlat Düğmesi Etiketi',
  'Stop Button Label' => 'Bitir Düğmesi Etiketi',
  'Start Button Color' => 'Başlat Düğmesi Rengi',
  'Stop Button Color' => 'Bitir Düğmesi Rengi',
  'Confirmation Mode' => 'Onay Modu',
  'None — Execute immediately' => 'Yok — Hemen çalıştır',
  'Confirm Dialog — Requires explicit click' => 'Onay Kutusu — Açık onay gerektirir',
  'Countdown — Auto-execute with cancel window' => 'Geri Sayım — İptal süresiyle otomatik çalıştır',
  'How to confirm actions before execution.' => 'Eylemler çalıştırılmadan önce nasıl onaylanacağı.',
  'Countdown Seconds' => 'Geri Sayım Süresi (saniye)',
  'Widget Configuration' => 'Widget Yapılandırması',

  // Frontend buttons
  'Start' => 'Başlat',
  'Done' => 'Tamamlandı',
  'Error' => 'Hata',
  'Confirm' => 'Onayla',
  'Cancel' => 'İptal',
  'Start working on ticket #%s?' => '#%s numaralı talep üzerinde çalışmaya başlansın mı?',
  'Complete and hand off ticket #%s?' => '#%s numaralı talep tamamlanıp devredilsin mi?',
  'Claim ticket and change status to working' => 'Talebi üstlen ve durumu «Devam ediyor» olarak değiştir',
  'Change status, release agent and transfer' => 'Durumu değiştir, temsilciyi serbest bırak ve devret',
  'Executing in %ss...' => '%s saniye içinde çalıştırılıyor...',
  'Undo' => 'Geri Al',
  'Undo expired' => 'Geri alma süresi doldu',
  'Start Selected' => 'Seçilenleri Başlat',
  'Complete Selected' => 'Seçilenleri Tamamla',
  'elapsed' => 'geçen süre',

  // Admin matrix
  'Department' => 'Departman',
  'Enabled' => 'Etkin',
  'Start: Trigger Status' => 'Başlat: Tetikleyici Durum',
  'Start: Target Status' => 'Başlat: Hedef Durum',
  'Stop: Target Status' => 'Bitir: Hedef Durum',
  'Stop: Transfer To' => 'Bitir: Devret',
  'Clear Team' => 'Ekibi Kaldır',
  '-- Select --' => '-- Seçin --',
  '-- None --' => '-- Yok --',

  // Workflow Builder
  'Workflow Builder' => 'İş Akışı Düzenleyici',
  'Back' => 'Geri',
  'Search departments...' => 'Departman ara...',
  'Enable All' => 'Tümünü Etkinleştir',
  'Disable All' => 'Tümünü Devre Dışı Bırak',
  '%d / %d enabled' => '%d / %d etkin',
  'Trigger' => 'Tetikleyici',
  'Working' => 'Devam ediyor',
  'Transfer to:' => 'Devret:',
  'Clear team on transfer' => 'Devirde ekibi kaldır',
  'Copy to...' => 'Şuraya kopyala...',
  'Apply template...' => 'Şablon uygula...',
  'Single Step' => 'Tek Adım',
  'Assembly Step 1 (no transfer)' => 'Montaj, adım 1 (devirsiz)',
  'Assembly Step 2 (with transfer)' => 'Montaj, adım 2 (devirli)',

  // Validation
  'Trigger status is required' => 'Tetikleyici durum zorunludur',
  'Working status is required' => '«Devam ediyor» durumu zorunludur',
  'Done status is required' => '«Tamamlandı» durumu zorunludur',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'Tetikleyici ve «Devam ediyor» aynı durum (Başlat düğmesi görünür bir şey yapmaz)',
  'Done status equals Trigger — this creates an infinite loop' => '«Tamamlandı» durumu tetikleyiciyle aynı — bu sonsuz döngü oluşturur',
  'Working and Done are the same status (Stop button will do nothing visible)' => '«Devam ediyor» ve «Tamamlandı» aynı durum (Bitir düğmesi görünür bir şey yapmaz)',

  // Footer / Save
  'No unsaved changes' => 'Kaydedilmemiş değişiklik yok',
  'Unsaved changes' => 'Kaydedilmemiş değişiklikler var',
  'All changes saved' => 'Tüm değişiklikler kaydedildi',
  'Save Changes' => 'Değişiklikleri Kaydet',
  'Saving...' => 'Kaydediliyor...',
  'Saved!' => 'Kaydedildi!',
  'Save failed' => 'Kaydetme başarısız',
  'Network error' => 'Ağ hatası',

  // Dialogs
  'Discard unsaved changes?' => 'Kaydedilmemiş değişiklikler silinsin mi?',
  'Copy this configuration to which department?' => 'Bu yapılandırma hangi departmana kopyalansın?',
  'Department not found: %s' => 'Departman bulunamadı: %s',
  'Copied to %s' => '%s departmanına kopyalandı',
  'Template applied — select statuses for each step' => 'Şablon uygulandı — her adım için durumları seçin',
  'Loading dashboard...' => 'Panel yükleniyor...',

  // Errors
  'Access Denied' => 'Erişim Engellendi',
  'Instance ID required' => 'Örnek kimliği gerekli',
  'Plugin not found' => 'Eklenti bulunamadı',
  'Instance not found' => 'Örnek bulunamadı',
  'Invalid JSON' => 'Geçersiz JSON',
  'Validation failed' => 'Doğrulama başarısız',
  'Configuration saved' => 'Yapılandırma kaydedildi',
  'Invalid widget' => 'Geçersiz widget',
  'Invalid action type' => 'Geçersiz eylem türü',
  'No tickets selected' => 'Talep seçilmedi',
  'Invalid or disabled widget' => 'Geçersiz veya devre dışı widget',
  'Invalid plugin instance' => 'Geçersiz eklenti örneği',
  'Configuration error' => 'Yapılandırma hatası',
  'Custom' => 'Özel',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'Her widget bir yardım konusunu yönetir. Bu konudaki talepler Başlat/Bitir düğmelerini gösterir.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'Başlat düğmesi araç ipucu için özel etiket. Varsayılan («Başlat») için boş bırakın.',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'Bitir düğmesi araç ipucu için özel etiket. Varsayılan («Tamamlandı») için boş bırakın.',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'Başlat düğmesinin hex renk kodu (ör. #128DBE). Varsayılan mavi için boş bırakın.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'Bitir düğmesinin hex renk kodu (ör. #27ae60). Varsayılan yeşil için boş bırakın.',
  'Per-department button configuration (managed by the UI below).' => 'Departman bazında düğme yapılandırması (aşağıdaki arayüz tarafından yönetilir).',
);
