// SPDX-License-Identifier: GPL-2.0-or-later
#include "settings.h"
#include <QApplication>
#include <QPluginLoader>
#include <KPluginFactory>
#include <KCModule>
#include <QComboBox>
#include <QSpinBox>
#include <cmath>
#include <QDBusConnection>
#include <QElapsedTimer>
#include <QThread>
class EffectReceiver : public QObject {
    Q_OBJECT
    Q_CLASSINFO("D-Bus Interface", "org.kde.kwin.Effects")
public:
    QString received;
public slots:
    void reconfigureEffect(const QString &name) { received = name; }
};
int main(int argc, char **argv) {
    QApplication app(argc, argv);
    EffectReceiver receiver;
    auto bus = QDBusConnection::sessionBus();
    if (!bus.registerService(QStringLiteral("org.kde.KWin")) ||
        !bus.registerObject(QStringLiteral("/Effects"), &receiver, QDBusConnection::ExportAllSlots)) return 9;
    QPluginLoader loader(QString::fromLocal8Bit(argv[1]));
    auto *factory = qobject_cast<KPluginFactory *>(loader.instance());
    if (!factory) return 1;
    auto *module = factory->create<KCModule>();
    if (!module) return 2;
    const auto combos = module->widget()->findChildren<QComboBox *>();
    const auto spins = module->widget()->findChildren<QSpinBox *>();
    if (combos.size() != 2 || spins.size() != 2) return 3;
    for (auto *combo : combos) if (combo->count() != 31) return 4;
    auto hasDefaults = [&] {
        return spins[0]->value() == 150 && spins[1]->value() == 720
            && combos[0]->currentText() == QLatin1String("easeOutBack")
            && combos[1]->currentText() == QLatin1String("easeOutElastic");
    };
    if (!hasDefaults()) return 5;
    spins[0]->setValue(42);
    combos[0]->setCurrentText(QStringLiteral("Linear"));
    module->defaults();
    if (!hasDefaults()) return 5;
    spins[0]->setValue(333);
    combos[1]->setCurrentText(QStringLiteral("easeOutElastic"));
    module->save();
    QElapsedTimer timer;
    timer.start();
    while (receiver.received.isEmpty() && timer.elapsed() < 2000) {
        app.processEvents();
        QThread::msleep(1);
    }
    if (receiver.received != QLatin1String("kwin_effect_clicktween")) return 10;
    module->load();
    if (spins[0]->value() != 333 || combos[1]->currentText() != QLatin1String("easeOutElastic")) return 6;
    for (const auto &entry : ClickTween::curves) {
        QEasingCurve curve(ClickTween::curveType(QString::fromLatin1(entry.name)));
        if (std::abs(curve.valueForProgress(0)) > 1e-6 || std::abs(curve.valueForProgress(1) - 1) > 1e-6) return 7;
        for (int i = 0; i <= 100; ++i) if (!std::isfinite(curve.valueForProgress(i / 100.0))) return 8;
    }
    delete module;
    return 0;
}

#include "check-config.moc"
