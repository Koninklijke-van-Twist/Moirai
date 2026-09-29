// SPDX-License-Identifier: GPL-2.0-or-later
#include "settings.h"
#include <KCModule>
#include <KPluginFactory>
#include <KSharedConfig>
#include <QComboBox>
#include <QDBusConnection>
#include <QDBusMessage>
#include <QDBusPendingCall>
#include <QFormLayout>
#include <QLabel>
#include <QSpinBox>

class ClickTweenConfig final : public KCModule
{
    Q_OBJECT
public:
    ClickTweenConfig(QObject *parent, const KPluginMetaData &data) : KCModule(parent, data) {
        auto *layout = new QFormLayout(widget());
        clickTime = new QSpinBox(widget());
        releaseTime = new QSpinBox(widget());
        clickEasing = new QComboBox(widget());
        releaseEasing = new QComboBox(widget());
        for (auto *spin : {clickTime, releaseTime}) {
            spin->setRange(0, 5000);
            spin->setSuffix(QStringLiteral(" ms"));
            spin->setToolTip(QStringLiteral("0 ms = direct; maximaal 5000 ms."));
            connect(spin, &QSpinBox::valueChanged, this, &ClickTweenConfig::changed);
        }
        for (auto *combo : {clickEasing, releaseEasing}) {
            for (const auto &curve : ClickTween::curves) combo->addItem(QString::fromLatin1(curve.name));
            combo->setMaxVisibleItems(16);
            connect(combo, &QComboBox::currentIndexChanged, this, &ClickTweenConfig::changed);
        }
        layout->addRow(QStringLiteral("Klik-tween-tijd (krimpen):"), clickTime);
        layout->addRow(QStringLiteral("Klik-easing:"), clickEasing);
        layout->addRow(QStringLiteral("Release-tween-tijd (teruggroeien):"), releaseTime);
        layout->addRow(QStringLiteral("Release-easing:"), releaseEasing);
        auto *help = new QLabel(QStringLiteral("De cursor krimpt bij indrukken naar 80% en groeit bij loslaten terug.\nBack en Elastic kunnen voorbij de eindgrootte veren.\nIn versnelt, Out remt af, InOut doet beide.\nWijzigingen gelden na Toepassen voor de volgende animatie."), widget());
        help->setWordWrap(true);
        layout->addRow(help);
        load();
    }
    void load() override {
        auto config = KSharedConfig::openConfig(QStringLiteral("kwinrc"));
        config->reparseConfiguration();
        saved.read(KConfigGroup(config, QStringLiteral("Effect-clicktween")));
        showSettings(saved);
    }
    void save() override {
        KConfigGroup group(KSharedConfig::openConfig(QStringLiteral("kwinrc")), QStringLiteral("Effect-clicktween"));
        group.writeEntry("ClickTweenTime", clickTime->value());
        group.writeEntry("ReleaseTweenTime", releaseTime->value());
        group.writeEntry("ClickEasing", clickEasing->currentText());
        group.writeEntry("ReleaseEasing", releaseEasing->currentText());
        group.sync();
        saved.read(group);
        changed();
        auto message = QDBusMessage::createMethodCall(QStringLiteral("org.kde.KWin"), QStringLiteral("/Effects"), QStringLiteral("org.kde.kwin.Effects"), QStringLiteral("reconfigureEffect"));
        message << QStringLiteral("kwin_effect_clicktween");
        QDBusConnection::sessionBus().asyncCall(message);
    }
    void defaults() override { showSettings(ClickTween::Settings{}); }
private:
    void showSettings(const ClickTween::Settings &s) {
        clickTime->setValue(s.clickTime);
        releaseTime->setValue(s.releaseTime);
        clickEasing->setCurrentText(s.clickEasing);
        releaseEasing->setCurrentText(s.releaseEasing);
        changed();
    }
    void changed() {
        setNeedsSave(clickTime->value() != saved.clickTime || releaseTime->value() != saved.releaseTime
            || clickEasing->currentText() != saved.clickEasing || releaseEasing->currentText() != saved.releaseEasing);
        const ClickTween::Settings defaults;
        setRepresentsDefaults(clickTime->value() == defaults.clickTime && releaseTime->value() == defaults.releaseTime
            && clickEasing->currentText() == defaults.clickEasing && releaseEasing->currentText() == defaults.releaseEasing);
    }
    QSpinBox *clickTime;
    QSpinBox *releaseTime;
    QComboBox *clickEasing;
    QComboBox *releaseEasing;
    ClickTween::Settings saved;
};
K_PLUGIN_CLASS_WITH_JSON(ClickTweenConfig, "config-metadata.json")
#include "clicktween_config.moc"
